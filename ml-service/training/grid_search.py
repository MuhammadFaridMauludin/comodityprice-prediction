
import argparse
import itertools
import json
import time

import joblib
import numpy as np
import pandas as pd
import tensorflow as tf
from tensorflow.keras import callbacks

from aggregate_weather import aggregate_weather
from config import GRID_SEARCH, MODELS, SAVE_DIR
from data import load_series, prepare
from models import build_model


def metrics(y_true, y_pred):
    """Menghitung metrik pada skala harga asli."""
    y_true = np.asarray(y_true).ravel()
    y_pred = np.asarray(y_pred).ravel()

    if len(y_true) != len(y_pred):
        raise ValueError(
            "Panjang aktual dan prediksi tidak sama."
        )

    err = y_true - y_pred

    return {
        "rmse": float(np.sqrt(np.mean(err ** 2))),
        "mae": float(np.mean(np.abs(err))),
        "mape": float(
            np.mean(np.abs(err / y_true)) * 100
        ),
    }


def predict_prices(model, d, split, target_mode):
    """Mengembalikan prediksi ke skala harga asli."""

    split_map = {
        "val": "va",
        "test": "te",
    }

    if split not in split_map:
        raise ValueError(
            "split harus 'val' atau 'test'."
        )

    suffix = split_map[split]
    X = d[f"X_{suffix}"]

    pred_scaled = model.predict(
        X,
        verbose=0,
    ).reshape(-1, 1)

    pred_target = d["y_scaler"].inverse_transform(
        pred_scaled
    ).ravel()

    if split == "val":
        y_true = d["y_true_price_va"]
        y_naive = d["previous_price_va"]
    else:
        y_true = d["y_true_price_te"]
        y_naive = d["previous_price_te"]

    if target_mode == "difference":
        y_pred = y_naive + pred_target
    else:
        y_pred = pred_target

    return y_true, y_pred, y_naive


def run_grid_search(args):
    SAVE_DIR.mkdir(parents=True, exist_ok=True)

    print("PROGRESS:AGREGASI_CUACA", flush=True)

    aggregate_weather(
        id_komoditas=args.komoditas,
        dari=args.dari,
        sampai=args.sampai,
    )

    print("PROGRESS:DATASET", flush=True)

    df, feature_cols = load_series(
        args.komoditas,
        args.wilayah,
        args.dari,
        args.sampai,
    )

    # Aktivasi menjadi bagian dari kombinasi eksperimen.
    combinations = list(itertools.product(
        GRID_SEARCH["window_size"],
        list(GRID_SEARCH["architectures"].items()),
        GRID_SEARCH["learning_rate"],
        GRID_SEARCH["batch_size"],
        GRID_SEARCH["activation"],
        GRID_SEARCH["dropout_rate"],
        GRID_SEARCH["training_strategies"],
    ))

    total = len(combinations)

    stem = (
        f"GRID_{args.model}"
        f"_k{args.komoditas}"
        f"_w{args.wilayah}"
        f"_target-{args.target_mode}"
    )

    results_path = SAVE_DIR / f"{stem}_results.csv"
    model_path = SAVE_DIR / f"{stem}_best.keras"
    x_scaler_path = SAVE_DIR / f"{stem}_x_scaler.pkl"
    y_scaler_path = SAVE_DIR / f"{stem}_y_scaler.pkl"
    summary_path = SAVE_DIR / f"{stem}_summary.json"

    results = []
    best_rmse = float("inf")
    best_config = None
    best_data = None

    for number, combo in enumerate(combinations, start=1):
        (
            window,
            (arch_name, units),
            lr,
            batch_size,
            activation,
            dropout_rate,
            training_strategy,
        ) = combo

        tf.keras.backend.clear_session()
        tf.keras.utils.set_random_seed(args.seed)

        print(
            f"PROGRESS:GRID_SEARCH:{number}/{total}",
            flush=True,
        )

        d = prepare(
            df,
            window,
            feature_cols,
            target_mode=args.target_mode,
        )

        model = build_model(
            kind=args.model,
            units=units,
            window=window,
            n_features=d["n_features"],
            lr=lr,
            dropout_rate=dropout_rate,
            activation=activation,
        )

        cbs = []
        patience = training_strategy["patience"]

        if patience is not None:
            cbs.append(callbacks.EarlyStopping(
                monitor="val_loss",
                patience=patience,
                restore_best_weights=True,
            ))

        start = time.time()

        history = model.fit(
            d["X_tr"],
            d["y_tr"],
            validation_data=(d["X_va"], d["y_va"]),
            epochs=training_strategy["epochs"],
            batch_size=batch_size,
            callbacks=cbs,
            verbose=0,
            shuffle=False,
        )

        duration = time.time() - start

        # Seleksi hyperparameter hanya dari validation.
        y_val, pred_val, naive_val = predict_prices(
            model,
            d,
            "val",
            args.target_mode,
        )

        val_metrics = metrics(y_val, pred_val)
        val_naive_metrics = metrics(y_val, naive_val)

        record = {
            "percobaan": number,
            "model": args.model,
            "arsitektur": arch_name,
            "units": ",".join(map(str, units)),
            "window_size": window,
            "learning_rate": lr,
            "batch_size": batch_size,
            "activation": activation,
            "dropout_rate": dropout_rate,
            "strategi_pelatihan": training_strategy["name"],
            "epoch_maksimum": training_strategy["epochs"],
            "epoch_aktual": len(history.history["loss"]),
            "early_stopping": patience is not None,
            "patience": patience,
            "seed": args.seed,
            "target_mode": args.target_mode,
            "jumlah_parameter": int(model.count_params()),
            "durasi_detik": round(duration, 2),
            "val_rmse": val_metrics["rmse"],
            "val_mae": val_metrics["mae"],
            "val_mape": val_metrics["mape"],
            "val_naive_rmse": val_naive_metrics["rmse"],
            "val_naive_mae": val_naive_metrics["mae"],
            "val_naive_mape": val_naive_metrics["mape"],
            "status_validasi": (
                "Mengungguli Naive"
                if val_metrics["rmse"]
                < val_naive_metrics["rmse"]
                else "Belum mengungguli Naive"
            ),
        }

        results.append(record)

        # Simpan progres secara berkala.
        if number % 5 == 0 or number == total:
            pd.DataFrame(results).to_csv(
                results_path,
                index=False,
            )

        # Model terbaik dipilih berdasarkan RMSE validation.
        if val_metrics["rmse"] < best_rmse:
            best_rmse = val_metrics["rmse"]
            best_config = record.copy()
            best_data = d

            model.save(model_path)

            joblib.dump(d["x_scaler"], x_scaler_path)
            joblib.dump(d["y_scaler"], y_scaler_path)

            print(
                "BEST_VALIDATION: "
                + json.dumps(
                    best_config,
                    ensure_ascii=False,
                ),
                flush=True,
            )

        del model

    if best_config is None or best_data is None:
        raise RuntimeError(
            "Grid Search tidak menghasilkan model."
        )

    # Test set dievaluasi setelah pemilihan konfigurasi.
    best_model = tf.keras.models.load_model(model_path)

    y_test, pred_test, naive_test = predict_prices(
        best_model,
        best_data,
        "test",
        args.target_mode,
    )

    test_metrics = metrics(y_test, pred_test)
    test_naive_metrics = metrics(y_test, naive_test)

    summary = {
        "model": args.model,
        "target_mode": args.target_mode,
        "jumlah_kombinasi": total,
        "kriteria_pemilihan": "RMSE validation terendah",
        "parameter_terbaik": best_config,
        "metrik_test": test_metrics,
        "metrik_naive_test": test_naive_metrics,
        "status_test": (
            "Mengungguli Naive berdasarkan RMSE"
            if test_metrics["rmse"]
            < test_naive_metrics["rmse"]
            else "Belum mengungguli Naive berdasarkan RMSE"
        ),
        "file_hasil_grid": str(results_path),
        "file_model": str(model_path),
    }

    summary_path.write_text(
        json.dumps(
            summary,
            indent=2,
            ensure_ascii=False,
        ),
        encoding="utf-8",
    )

    print("PROGRESS:SELESAI", flush=True)
    print(json.dumps(summary, indent=2, ensure_ascii=False))

    return summary


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Grid Search LSTM, BiLSTM, atau GRU"
    )

    parser.add_argument("--komoditas", type=int, required=True)
    parser.add_argument("--wilayah", type=int, required=True)
    parser.add_argument("--dari", default="2021-01-01")
    parser.add_argument("--sampai", required=True)

    parser.add_argument(
        "--model",
        choices=MODELS,
        default="BiLSTM",
    )

    parser.add_argument("--seed", type=int, default=42)

    parser.add_argument(
        "--target-mode",
        choices=["price", "difference"],
        default="difference",
    )

    run_grid_search(parser.parse_args())
