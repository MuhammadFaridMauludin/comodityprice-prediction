import argparse
import json
import time

import joblib
import numpy as np
import tensorflow as tf
from tensorflow.keras import callbacks
from pathlib import Path
from aggregate_weather import aggregate_weather
from config import (
    ARCHITECTURES,
    FIXED,
    MODELS,
    SAVE_DIR,
    STRATEGIES,
    scenario_code,
)
from data import load_series, prepare
from models import build_model


FITUR = {
    "semua": None,
    "harga": ["harga"],
    "harga+kalender": [
        "harga",
        "is_ramadan",
        "pre_eid",
        "is_adha",
        "is_nataru",
    ],
    "harga+cuaca": [
        "harga",
        "suhu",
        "kelembapan",
        "curah_hujan",
    ],
}


def metrics(y_true, y_pred):
    """Menghitung RMSE, MAE, dan MAPE."""
    y_true = np.asarray(y_true).reshape(-1)
    y_pred = np.asarray(y_pred).reshape(-1)

    err = y_true - y_pred

    return {
        "rmse": float(np.sqrt(np.mean(err ** 2))),
        "mae": float(np.mean(np.abs(err))),
        "mape": float(
            np.mean(np.abs(err / y_true)) * 100
        ),
    }


def metrics_per_step(y_true, y_pred):
    """Menghitung metrik untuk setiap langkah horizon."""
    return {
        f"h{h + 1}": metrics(
            y_true[:, h],
            y_pred[:, h],
        )
        for h in range(y_true.shape[1])
    }


def to_price(model, X, last_price, d, target_mode):
    """Mengubah prediksi ke skala harga asli."""
    pred_scaled = model.predict(X, verbose=0)
    N, H = pred_scaled.shape

    pred = d["y_scaler"].inverse_transform(
        pred_scaled.reshape(-1, 1)
    ).reshape(N, H)

    if target_mode == "difference":
        # Harga prediksi = harga terakhir yang diketahui
        # + akumulasi prediksi perubahan harga.
        pred = (
            last_price[:, None]
            + np.cumsum(pred, axis=1)
        )

    return pred


def run(args):
    # Mengatur seed untuk Python, NumPy, dan TensorFlow.
    tf.keras.utils.set_random_seed(args.seed)

    window = (
        args.window
        if args.window is not None
        else FIXED["window_size"]
    )

    lr = (
        args.lr
        if args.lr is not None
        else FIXED["learning_rate"]
    )

    horizon = args.horizon

    # 1. Agregasi data cuaca.
    print("PROGRESS:AGREGASI_CUACA", flush=True)

    aggregate_weather(
        id_komoditas=args.komoditas,
        dari=args.dari,
        sampai=args.sampai,
    )

    # 2. Memuat data harga dan cuaca.
    print("PROGRESS:DATASET", flush=True)

    df, feature_cols = load_series(
        args.komoditas,
        args.wilayah,
        args.dari,
        args.sampai,
    )

    # 3. Memilih kelompok fitur.
    fitur_pilihan = FITUR[args.fitur]

    if fitur_pilihan is not None:
        missing = (
            set(fitur_pilihan) - set(feature_cols)
        )

        if missing:
            raise ValueError(
                f"Fitur tidak tersedia: {sorted(missing)}"
            )

        feature_cols = fitur_pilihan

    # 4. Menyiapkan data latih, validasi, dan uji.
    print("PROGRESS:SPLIT", flush=True)

    d = prepare(
        df,
        window,
        feature_cols,
        target_mode=args.target_mode,
        horizon=horizon,
    )

    # 5. Mengambil konfigurasi arsitektur dan strategi.
    cfg = ARCHITECTURES[args.arsitektur][args.model]

    units = cfg["units"]
    dropout = cfg["dropout"]
    st = STRATEGIES[args.strategi]

    kode = scenario_code(
        args.model,
        args.arsitektur,
        args.strategi,
    )

    # 6. Membangun model.
    model = build_model(
        args.model,
        units,
        window,
        n_features=d["n_features"],
        lr=lr,
        dropout_rate=dropout,
        horizon=horizon,
        dense_units=args.dense,
    )

    # 7. Callback pelatihan.
    cbs = []

    if st["patience"] is not None:
        cbs.append(
            callbacks.EarlyStopping(
                monitor="val_loss",
                patience=st["patience"],
                restore_best_weights=True,
            )
        )

    # 8. Melatih model.
    t0 = time.time()

    print("PROGRESS:TRAINING", flush=True)

    hist = model.fit(
        d["X_tr"],
        d["y_tr"],
        validation_data=(
            d["X_va"],
            d["y_va"],
        ),
        epochs=st["epochs"],
        batch_size=FIXED["batch_size"],
        callbacks=cbs,
        verbose=0,
        shuffle=False,
    )

    durasi = time.time() - t0

    # 9. Evaluasi model.
    print("PROGRESS:EVALUASI", flush=True)

    y_pred_va = to_price(
        model,
        d["X_va"],
        d["last_price_va"],
        d,
        args.target_mode,
    )

    y_pred = to_price(
        model,
        d["X_te"],
        d["last_price_te"],
        d,
        args.target_mode,
    )

    y_true_va = d["y_true_price_va"]
    y_naive_va = d["naive_price_va"]

    y_true = d["y_true_price_te"]
    y_naive = d["naive_price_te"]

    # 10. Menyimpan model dan scaler.
    SAVE_DIR.mkdir(
        parents=True,
        exist_ok=True,
    )

    stem = (
        f"{kode}"
        f"_k{args.komoditas}"
        f"_w{args.wilayah}"
        f"_target-{args.target_mode}"
        f"_win{window}"
        f"_h{horizon}"
        f"_lr{lr}"
        f"_d{args.dense}"
        f"_f{args.fitur}"
        f"_seed{args.seed}"
    )

    model.save(
        SAVE_DIR / f"{stem}.keras"
    )

    joblib.dump(
        d["x_scaler"],
        SAVE_DIR / f"{stem}_x_scaler.pkl",
    )

    joblib.dump(
        d["y_scaler"],
        SAVE_DIR / f"{stem}_y_scaler.pkl",
    )

    # 11. Menyusun hasil evaluasi.
    out = {
        "kode_skenario": kode,
        "id_komoditas": args.komoditas,
        "id_wilayah": args.wilayah,
        "model": args.model,
        "arsitektur": args.arsitektur,
        "units": ",".join(map(str, units)),
        "dropout": dropout,
        "dense_units": args.dense,
        "strategi": args.strategi,
        "epoch_maks": st["epochs"],
        "epoch_aktual": len(hist.history["loss"]),
        "early_stopping": (
            st["patience"] is not None
        ),
        "patience": st["patience"],
        "jumlah_parameter": int(
            model.count_params()
        ),
        "durasi_detik": round(durasi, 1),
        "seed": args.seed,
        "window_size": window,
        "horizon": horizon,
        "batch_size": FIXED["batch_size"],
        "learning_rate": lr,
        "fitur_mode": args.fitur,
        "fitur": feature_cols,
        "data": {
            "total": d["n_total"],
            "latih": d["n_train"],
            "validasi": d["n_val"],
            "uji": d["n_test"],
        },
        "val_loss_terbaik": float(
            min(hist.history["val_loss"])
        ),
        "metrik_validasi": metrics(
            y_true_va,
            y_pred_va,
        ),
        "metrik_naive_validasi": metrics(
            y_true_va,
            y_naive_va,
        ),
        "metrik": metrics(
            y_true,
            y_pred,
        ),
        "metrik_naive": metrics(
            y_true,
            y_naive,
        ),
        "metrik_per_langkah": metrics_per_step(
            y_true,
            y_pred,
        ),
        "metrik_naive_per_langkah": metrics_per_step(
            y_true,
            y_naive,
        ),
        "target_mode": args.target_mode,
    }

    print(
        json.dumps(
            out,
            indent=2,
            ensure_ascii=False,
        )
    )
    Path("hasil").mkdir(exist_ok=True)
    Path("hasil", f"{stem}_s{args.seed}.json").write_text(
        json.dumps(out, indent=2, ensure_ascii=False), encoding="utf-8"
    )

    return out

if __name__ == "__main__":
    p = argparse.ArgumentParser()

    p.add_argument(
        "--komoditas",
        type=int,
        required=True,
    )

    p.add_argument(
        "--wilayah",
        type=int,
        required=True,
    )

    p.add_argument(
        "--dari",
        default="2021-01-01",
    )

    p.add_argument(
        "--sampai",
        required=True,
    )

    p.add_argument(
        "--model",
        choices=MODELS,
        default="LSTM",
    )

    p.add_argument(
        "--arsitektur",
        choices=list(ARCHITECTURES),
        default="K",
    )

    p.add_argument(
        "--strategi",
        choices=list(STRATEGIES),
        default="ST-3",
    )

    p.add_argument(
        "--seed",
        type=int,
        default=42,
    )

    p.add_argument(
        "--target-mode",
        choices=["price", "difference"],
        default="price",
        help=(
            "Target prediksi: harga langsung "
            "atau selisih harga."
        ),
    )

    p.add_argument(
        "--horizon",
        type=int,
        default=1,
        help=(
            "Jumlah hari ke depan yang diprediksi "
            "sekaligus, misalnya 1, 7, atau 30."
        ),
    )

    p.add_argument(
        "--window",
        type=int,
        default=None,
        help=(
            "Jumlah hari historis. "
            "Default mengikuti FIXED['window_size']."
        ),
    )

    p.add_argument(
        "--dense",
        type=int,
        default=0,
        help=(
            "Unit Dense tengah. "
            "0 berarti tanpa Dense tengah."
        ),
    )

    p.add_argument(
        "--lr",
        type=float,
        default=None,
        help=(
            "Learning rate. "
            "Default mengikuti FIXED['learning_rate']."
        ),
    )

    p.add_argument(
        "--fitur",
        choices=list(FITUR),
        default="semua",
        help="Kelompok fitur yang digunakan untuk training.",
    )

    run(p.parse_args())
