
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
    err = y_true - y_pred

    return {
        "rmse": float(np.sqrt(np.mean(err ** 2))),
        "mae": float(np.mean(np.abs(err))),
        "mape": float(
            np.mean(np.abs(err / y_true)) * 100
        ),
    }


def run(args):
    tf.keras.utils.set_random_seed(args.seed)
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
        FIXED["window_size"],
        feature_cols,
    )

    units = ARCHITECTURES[args.arsitektur]
    st = STRATEGIES[args.strategi]

    kode = scenario_code(
        args.model,
        args.arsitektur,
        args.strategi,
    )
    
    model = build_model(
        args.model,
        units,
        FIXED["window_size"],
        n_features=d["n_features"],
        lr=FIXED["learning_rate"],
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
    # Prediksi dan evaluasi pada skala harga asli.
    y_true = d["y_scaler"].inverse_transform(
        d["y_te"].reshape(-1, 1)
    ).ravel()

    y_pred = d["y_scaler"].inverse_transform(
        model.predict(d["X_te"], verbose=0)
    ).ravel()

    # Baseline: harga terakhir pada window.
    y_naive = d["y_scaler"].inverse_transform(
        d["X_te"][:, -1, 0].reshape(-1, 1)
    ).ravel()

    SAVE_DIR.mkdir(parents=True, exist_ok=True)
    stem = f"{kode}_k{args.komoditas}_w{args.wilayah}"

    model.save(SAVE_DIR / f"{stem}.keras")
    joblib.dump(
        d["x_scaler"],
        SAVE_DIR / f"{stem}_x_scaler.pkl",
    )
    joblib.dump(
        d["y_scaler"],
        SAVE_DIR / f"{stem}_y_scaler.pkl",
    )

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
        "window_size": FIXED["window_size"],
        "batch_size": FIXED["batch_size"],
        "learning_rate": FIXED["learning_rate"],
        "fitur": feature_cols,
        "data": {
            "total": d["n_total"],
            "latih": d["n_train"],
            "validasi": d["n_val"],
            "uji": d["n_test"],
        },
        "metrik": metrics(y_true, y_pred),
        "metrik_naive": metrics(y_true, y_naive),
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

    run(p.parse_args())
