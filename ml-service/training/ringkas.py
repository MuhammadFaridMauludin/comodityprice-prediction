import glob, json, itertools
import pandas as pd
from scipy.stats import ttest_ind, mannwhitneyu, ttest_1samp

KEYS = ["model", "fitur", "window", "horizon", "target"]

rows = []
for f in glob.glob("hasil/*.json"):
    r = json.load(open(f, encoding="utf-8"))
    mv = r.get("metrik_validasi", {})
    nv = r.get("metrik_naive_validasi", {})
    rows.append({
        "model": r["model"],
        "fitur": r.get("fitur_mode", "semua"),
        "window": r["window_size"],
        "horizon": r["horizon"],
        "target": r["target_mode"],
        "seed": r["seed"],
        "rmse": r["metrik"]["rmse"],
        "naive_rmse": r["metrik_naive"]["rmse"],
        "mae": r["metrik"]["mae"],
        "naive_mae": r["metrik_naive"]["mae"],
        "mape": r["metrik"]["mape"],
        "naive_mape": r["metrik_naive"]["mape"],
        "val_rmse": mv.get("rmse"),
        "naive_val_rmse": nv.get("rmse"),
    })

df = pd.DataFrame(rows)

# peringatan jika ada hasil ganda untuk konfigurasi + seed yang sama
dup = df.duplicated(KEYS + ["seed"], keep=False)
if dup.any():
    print("PERINGATAN: ada hasil ganda (konfigurasi + seed sama):")
    print(df[dup].sort_values(KEYS + ["seed"])[KEYS + ["seed", "rmse"]].to_string())
    print()

df["rmse_vs_naive"] = (df["rmse"] / df["naive_rmse"] - 1) * 100
df["mae_vs_naive"] = (df["mae"] / df["naive_mae"] - 1) * 100
df["mape_vs_naive"] = (df["mape"] / df["naive_mape"] - 1) * 100
df["val_vs_naive"] = (df["val_rmse"] / df["naive_val_rmse"] - 1) * 100
df["menang_rmse"] = df["rmse"] < df["naive_rmse"]

g = df.groupby(KEYS).agg(
    n_seed=("seed", "count"),
    val_rmse_mean=("val_rmse", "mean"),
    val_rmse_sd=("val_rmse", "std"),
    val_vs_naive=("val_vs_naive", "mean"),
    rmse_mean=("rmse", "mean"),
    rmse_sd=("rmse", "std"),
    rmse_vs_naive=("rmse_vs_naive", "mean"),
    mae_vs_naive=("mae_vs_naive", "mean"),
    mape_vs_naive=("mape_vs_naive", "mean"),
    seed_menang=("menang_rmse", "sum"),
)

# uji t satu sampel: apakah RMSE model berbeda dari RMSE naive (konstan)
p_naive = {}
for k, sub in df.groupby(KEYS):
    if len(sub) >= 3:
        p_naive[k] = ttest_1samp(sub["rmse"], sub["naive_rmse"].mean()).pvalue
g["p_vs_naive"] = pd.Series(p_naive)
g = g.round(3)

print(g.to_string())
g.to_csv("ringkasan.csv")

# uji antar fitur (RMSE uji), untuk model/window/horizon/target yang sama
uji = []
for (model, window, horizon, target), sub in df.groupby(["model", "window", "horizon", "target"]):
    pasangan = list(itertools.combinations(sorted(sub["fitur"].unique()), 2))
    for a, b in pasangan:
        xa = sub.loc[sub["fitur"] == a, "rmse"]
        xb = sub.loc[sub["fitur"] == b, "rmse"]
        if len(xa) < 3 or len(xb) < 3:
            continue
        p_w = ttest_ind(xa, xb, equal_var=False).pvalue
        p_m = mannwhitneyu(xa, xb).pvalue
        uji.append({
            "model": model, "window": window, "horizon": horizon, "target": target,
            "fitur_a": a, "fitur_b": b,
            "selisih_rmse_a_minus_b": xa.mean() - xb.mean(),
            "p_welch": p_w,
            "p_mwu": p_m,
            "p_welch_bonferroni": min(p_w * len(pasangan), 1.0),
        })

if uji:
    u = pd.DataFrame(uji).round(4)
    print("\n=== UJI ANTAR FITUR (selisih negatif = fitur_a lebih baik) ===")
    print(u.to_string(index=False))
    u.to_csv("uji_statistik.csv", index=False)