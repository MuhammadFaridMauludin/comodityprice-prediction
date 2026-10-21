import argparse
import json
import time

import joblib
import numpy as np
import tensorflow as tf
from tensorflow.keras import callbacks
from aggregate_weather import aggregate_weather

from config import (
    ARCHITECTURES, FIXED, MODELS, SAVE_DIR,
    STRATEGIES, scenario_code
)
from data import load_series, prepare
from models import build_model


def metrics(y_true, y_pred):
    """Metrik pada array berbentuk apa pun (diratakan)."""
    err = y_true - y_pred

    return {
        "rmse": float(np.sqrt(np.mean(err ** 2))),
        "mae": float(np.mean(np.abs(err))),
        "mape": float(np.mean(np.abs(err / y_true)) * 100),
    }


def metrics_per_step(y_true, y_pred):
    """Metrik untuk tiap langkah horizon (h1, h2, ...)."""
    return {
        f"h{h + 1}": metrics(y_true[:, h], y_pred[:, h])
        for h in range(y_true.shape[1])
    }


def run(args):
    tf.keras.utils.set_random_seed(args.seed)

    window = args.window or FIXED["window_size"]
    horizon = args.horizon

    print("PROGRESS:AGREGASI_CUACA", flush=True)
    # Tahap 1: Agregasikan cuaca secara otomatis
    aggregate_weather(
        id_komoditas=args.komoditas,
        dari=args.dari,
        sampai=args.sampai,
    )

    print("PROGRESS:DATASET", flush=True)
    # Tahap 2: Ambil data harga dan cuaca untuk model
    df, feature_cols = load_series(
        args.komoditas,
        args.wilayah,
        args.dari,
        args.sampai,
    )

    print("PROGRESS:SPLIT", flush=True)
    d = prepare(
        df,
        window,
        feature_cols,
        target_mode=args.target_mode,
        horizon=horizon,
    )

    units = ARCHITECTURES[args.arsitektur]
    st = STRATEGIES[args.strategi]

    kode = scenario_code(args.model, args.arsitektur, args.strategi)

    model = build_model(
        args.model,
        units,
        window,
        n_features=d["n_features"],
        lr=FIXED["learning_rate"],
        horizon=horizon,
    )

    cbs = []

    if st["patience"] is not None:
        cbs.append(callbacks.EarlyStopping(
            monitor="val_loss",
            patience=st["patience"],
            restore_best_weights=True,
        ))

    t0 = time.time()
    print("PROGRESS:TRAINING", flush=True)
    hist = model.fit(
        d["X_tr"],
        d["y_tr"],
        validation_data=(d["X_va"], d["y_va"]),
        epochs=st["epochs"],
        batch_size=FIXED["batch_size"],
        callbacks=cbs,
        verbose=0,
        shuffle=False,
    )

    durasi = time.time() - t0
    print("PROGRESS:EVALUASI", flush=True)

    # Prediksi pada skala training: (N, horizon).
    pred_scaled = model.predict(d["X_te"], verbose=0)
    N, H = pred_scaled.shape

    # Kembalikan ke skala asli target.
    pred_target = d["y_scaler"].inverse_transform(
        pred_scaled.reshape(-1, 1)
    ).reshape(N, H)

    # Harga aktual dan baseline naive, keduanya (N, horizon).
    y_true = d["y_true_price_te"]
    y_naive = d["naive_price_te"]

    if args.target_mode == "difference":
        # Target model = perubahan harga harian.
        # Harga hari ke-h = harga terakhir yang diketahui
        # + jumlah kumulatif perubahan sampai hari ke-h.
        y_pred = d["last_price_te"][:, None] + np.cumsum(pred_target, axis=1)
    else:
        y_pred = pred_target

    SAVE_DIR.mkdir(parents=True, exist_ok=True)
    stem = (
        f"{kode}_k{args.komoditas}_w{args.wilayah}"
        f"_target-{args.target_mode}"
        f"_win{window}_h{horizon}"
    )

    model.save(SAVE_DIR / f"{stem}.keras")
    joblib.dump(d["x_scaler"], SAVE_DIR / f"{stem}_x_scaler.pkl")
    joblib.dump(d["y_scaler"], SAVE_DIR / f"{stem}_y_scaler.pkl")

    out = {
        "kode_skenario": kode,
        "id_komoditas": args.komoditas,
        "id_wilayah": args.wilayah,
        "model": args.model,
        "arsitektur": args.arsitektur,
        "units": ",".join(map(str, units)),
        "strategi": args.strategi,
        "epoch_maks": st["epochs"],
        "epoch_aktual": len(hist.history["loss"]),
        "early_stopping": st["patience"] is not None,
        "patience": st["patience"],
        "jumlah_parameter": int(model.count_params()),
        "durasi_detik": round(durasi, 1),
        "seed": args.seed,
        "window_size": window,
        "horizon": horizon,
        "batch_size": FIXED["batch_size"],
        "learning_rate": FIXED["learning_rate"],
        "fitur": feature_cols,
        "data": {
            "total": d["n_total"],
            "latih": d["n_train"],
            "validasi": d["n_val"],
            "uji": d["n_test"],
        },
        # Rata-rata atas seluruh langkah horizon.
        "metrik": metrics(y_true, y_pred),
        "metrik_naive": metrics(y_true, y_naive),
        # Akurasi per hari ke depan (h1 = besok, h7 = 7 hari lagi, ...).
        "metrik_per_langkah": metrics_per_step(y_true, y_pred),
        "metrik_naive_per_langkah": metrics_per_step(y_true, y_naive),
        "target_mode": args.target_mode,
    }

    print(json.dumps(out, indent=2, ensure_ascii=False))
    return out


if __name__ == "__main__":
    p = argparse.ArgumentParser()

    p.add_argument("--komoditas", type=int, required=True)
    p.add_argument("--wilayah", type=int, required=True)
    p.add_argument("--dari", default="2021-01-01")
    p.add_argument("--sampai", required=True)
    p.add_argument("--model", choices=MODELS, default="LSTM")
    p.add_argument(
        "--arsitektur",
        choices=list(ARCHITECTURES),
        default="A",
    )
    p.add_argument(
        "--strategi",
        choices=list(STRATEGIES),
        default="ST-1",
    )
    p.add_argument("--seed", type=int, default=42)

    p.add_argument(
        "--target-mode",
        choices=["price", "difference"],
        default="price",
        help="Target prediksi: harga langsung atau selisih harga.",
    )
    p.add_argument(
        "--horizon",
        type=int,
        default=1,
        help="Jumlah hari ke depan yang diprediksi sekaligus (1, 7, 30).",
    )
    p.add_argument(
        "--window",
        type=int,
        default=None,
        help="Jumlah hari historis sebagai input. "
             "Default: FIXED['window_size'] di config.py.",
    )
    run(p.parse_args())