"""
Menjalankan semua skenario (model x arsitektur x strategi) sekaligus.

Contoh:
    python run_all.py --komoditas 1 --wilayah 1 --sampai 2026-09-30
    python run_all.py --komoditas 1 --wilayah 1 --sampai 2026-09-30 --horizon 7 --window 14
    python run_all.py --komoditas 1 --wilayah 1 --sampai 2026-09-30 --model GRU --strategi ST-3
"""

import argparse
import json
import time
import traceback
from argparse import Namespace

import pandas as pd
import tensorflow as tf

from config import ARCHITECTURES, MODELS, SAVE_DIR, STRATEGIES, scenario_code
from train import run


def main():
    p = argparse.ArgumentParser()
    p.add_argument("--komoditas", type=int, required=True)
    p.add_argument("--wilayah", type=int, required=True)
    p.add_argument("--dari", default="2021-01-01")
    p.add_argument("--sampai", required=True)
    p.add_argument("--seed", type=int, default=42)
    p.add_argument("--target-mode", choices=["price", "difference"], default="price")
    p.add_argument("--horizon", type=int, default=1)
    p.add_argument("--window", type=int, default=None)

    # Filter opsional. Tanpa filter = semua skenario (18).
    p.add_argument("--model", nargs="+", choices=MODELS, default=MODELS)
    p.add_argument("--arsitektur", nargs="+", choices=list(ARCHITECTURES),
                   default=list(ARCHITECTURES))
    p.add_argument("--strategi", nargs="+", choices=list(STRATEGIES),
                   default=list(STRATEGIES))
    a = p.parse_args()

    jobs = [
        (m, ar, st)
        for ar in a.arsitektur
        for st in a.strategi
        for m in a.model
    ]
    print(f"Total skenario: {len(jobs)}\n", flush=True)

    rows, gagal = [], []
    t_all = time.time()

    for n, (m, ar, st) in enumerate(jobs, start=1):
        kode = scenario_code(m, ar, st)
        print(f"\n{'=' * 60}\n[{n}/{len(jobs)}] {kode}: {m} | {ar} | {st}\n{'=' * 60}",
              flush=True)

        args = Namespace(
            komoditas=a.komoditas,
            wilayah=a.wilayah,
            dari=a.dari,
            sampai=a.sampai,
            model=m,
            arsitektur=ar,
            strategi=st,
            seed=a.seed,
            target_mode=a.target_mode,
            horizon=a.horizon,
            window=a.window,
        )

        try:
            out = run(args)
            rows.append({
                "kode": out["kode_skenario"],
                "model": out["model"],
                "arsitektur": out["arsitektur"],
                "strategi": out["strategi"],
                "horizon": out["horizon"],
                "window": out["window_size"],
                "epoch_aktual": out["epoch_aktual"],
                "durasi_detik": out["durasi_detik"],
                "rmse": out["metrik"]["rmse"],
                "mae": out["metrik"]["mae"],
                "mape": out["metrik"]["mape"],
                "mape_naive": out["metrik_naive"]["mape"],
            })
        except Exception:
            # Satu skenario gagal tidak menghentikan yang lain.
            traceback.print_exc()
            gagal.append(kode)
        finally:
            # Bebaskan memori antar skenario.
            tf.keras.backend.clear_session()

    if rows:
        df = pd.DataFrame(rows).sort_values("kode")
        SAVE_DIR.mkdir(parents=True, exist_ok=True)
        path = SAVE_DIR / (
            f"ringkasan_k{a.komoditas}_w{a.wilayah}"
            f"_target-{a.target_mode}_h{a.horizon}.csv"
        )
        df.to_csv(path, index=False)

        print("\n\n=== RINGKASAN ===")
        print(df.to_string(index=False, float_format=lambda x: f"{x:,.4f}"))
        print(f"\nDisimpan: {path}")

    print(f"\nSelesai dalam {(time.time() - t_all) / 60:.1f} menit.")
    if gagal:
        print(f"Skenario gagal: {gagal}")


if __name__ == "__main__":
    main()