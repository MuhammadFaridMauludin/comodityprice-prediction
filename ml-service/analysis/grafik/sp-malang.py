from pathlib import Path
import pandas as pd
import seaborn as sns
import matplotlib.pyplot as plt

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent.parent

#konfigurasi
FILE_HARGA = PROJECT_ROOT / "data" / "price" / "harga-cabaikeriting.csv"
FILE_CUACA = PROJECT_ROOT / "data" / "weather" / "cuaca-malang.csv"
OUTPUT_FOLDER = PROJECT_ROOT / "analysis" / "hasil"

WILAYAH_SEKITAR_MALANG = [
    "Kabupaten Malang", "Kota Malang", "Kota Batu",
    "Kabupaten Mojokerto", "Kota Mojokerto",
    "Kabupaten Pasuruan", "Kota Pasuruan",
    "Kabupaten Jombang",
    "Kabupaten Probolinggo", "Kota Probolinggo",
    "Kabupaten Lumajang",
    "Kabupaten Blitar", "Kota Blitar",
    "Kabupaten Kediri", "Kota Kediri",
    "Kota Surabaya",
    "Kabupaten Gresik",
]

MAX_LAG = 90

sns.set_theme(style="whitegrid")
plt.rcParams.update({
    'font.size': 11,
    'figure.dpi': 150,
    'savefig.dpi': 300,
})

#prepocessing data cuaca
print("[1/5] Memproses data cuaca harian malang...")
daily_weather = pd.read_csv(FILE_CUACA)
daily_weather['tanggal'] = pd.to_datetime(daily_weather['tanggal'])

#prepocessing data harga
print("[2/5] Memproses data harga cabai...")
df_price_raw = pd.read_csv(FILE_HARGA)
df_price_raw['tanggal'] = pd.to_datetime(df_price_raw['tanggal'])

wilayah_tersedia = [w for w in WILAYAH_SEKITAR_MALANG if w in df_price_raw.columns]
wilayah_hilang = [w for w in WILAYAH_SEKITAR_MALANG if w not in df_price_raw.columns]
if wilayah_hilang:
    print("Peringatan, kolom tidak ditemukan di CSV harga:", wilayah_hilang)
print(f"-> Wilayah dianalisis ({len(wilayah_tersedia)}): {wilayah_tersedia}")

df_price = df_price_raw[['tanggal'] + wilayah_tersedia].copy()
for w in wilayah_tersedia:
    df_price[w] = pd.to_numeric(df_price[w], errors='coerce')

#datasaet digabung
df_merged = pd.merge(daily_weather, df_price, on='tanggal', how='inner')
df_merged = df_merged.sort_values('tanggal').reset_index(drop=True)
print(f"[3/5] Total data harian tersinkron: {len(df_merged)} hari.")

if len(df_merged) == 0:
    raise ValueError("Merge kosong, cek rentang tanggal & format kedua file.")

#hitung korelasi lag
print(f"[4/5] Menghitung korelasi lag (0-{MAX_LAG} hari) untuk tiap wilayah...")

hasil_per_wilayah = {}
ringkasan_peak = []

for wilayah in wilayah_tersedia:
    lag_rows = []
    for lag in range(0, MAX_LAG + 1):
        temp_lagged = df_merged['suhu'].shift(lag)
        hum_lagged = df_merged['kelembapan'].shift(lag)
        rain_lagged = df_merged['curah_hujan'].shift(lag)

        lag_rows.append({
            'lag_hari': lag,
            'suhu': df_merged[wilayah].corr(temp_lagged),
            'kelembapan': df_merged[wilayah].corr(hum_lagged),
            'curah_hujan': df_merged[wilayah].corr(rain_lagged),
        })

    df_lag = pd.DataFrame(lag_rows)
    hasil_per_wilayah[wilayah] = df_lag

    best_row = None
    best_val = 0
    best_var = None
    for var in ['suhu', 'kelembapan', 'curah_hujan']:
        idx = df_lag[var].abs().idxmax()
        val = df_lag.loc[idx, var]
        if abs(val) > abs(best_val):
            best_val = val
            best_row = df_lag.loc[idx, 'lag_hari']
            best_var = var

    ringkasan_peak.append({
        'wilayah': wilayah,
        'variabel_terkuat': best_var,
        'lag_hari_terkuat': int(best_row),
        'korelasi_terkuat': round(best_val, 3),
    })
#ringkasan
print("\n" + "=" * 65)
print("RINGKASAN: KANDIDAT SENTRA PRODUKSI (diurutkan |korelasi| terkuat)")
print("=" * 65)

df_ringkasan = pd.DataFrame(ringkasan_peak)
df_ringkasan['abs_korelasi'] = df_ringkasan['korelasi_terkuat'].abs()
df_ringkasan = df_ringkasan.sort_values('abs_korelasi', ascending=False).drop(columns='abs_korelasi')
print(df_ringkasan.to_string(index=False))

kandidat_sentra = df_ringkasan.iloc[0]['wilayah']
print(f"\n>> Kandidat kuat sentra produksi (korelasi cuaca-harga terbesar): {kandidat_sentra}")
print(">> CATATAN: ini indikasi statistik, perlu dikonfirmasi dengan data/literatur")
print("   produksi pertanian aktual (mis. BPS/Dinas Pertanian) sebelum disimpulkan final.")

df_ringkasan.to_csv(OUTPUT_FOLDER / "ringkasan_korelasimalang.csv", index=False)
print("\nRingkasan disimpan ke: analysis/hasil/ringkasan_korelasimalang.csv")

#visualisasi
print("\n[5/5] Menampilkan visualisasi grafik korelasi seluruh wilayah...")

n_wilayah = len(wilayah_tersedia)
n_cols = 3
n_rows = -(-n_wilayah // n_cols)

fig, axes = plt.subplots(n_rows, n_cols, figsize=(6 * n_cols, 4.5 * n_rows))
axes = axes.flatten()

for i, wilayah in enumerate(wilayah_tersedia):
    df_lag = hasil_per_wilayah[wilayah]
    ax = axes[i]

    ax.plot(df_lag['lag_hari'], df_lag['kelembapan'], label='Kelembapan', color='#1f77b4', linewidth=2)
    ax.plot(df_lag['lag_hari'], df_lag['curah_hujan'], label='Curah Hujan', color='#2ca02c', linewidth=2)
    ax.plot(df_lag['lag_hari'], df_lag['suhu'], label='Suhu', color='#d62728', linewidth=2)

    peak_info = df_ringkasan[df_ringkasan['wilayah'] == wilayah].iloc[0]
    ax.axvline(x=peak_info['lag_hari_terkuat'], color='black', linestyle='--', alpha=0.6)

    ax.set_title(
        f"{wilayah}\n(peak: {peak_info['variabel_terkuat']} @ lag {peak_info['lag_hari_terkuat']}, r={peak_info['korelasi_terkuat']})",
        fontsize=10, fontweight='bold'
    )
    ax.set_xlabel('Lag (hari)', fontsize=9)
    ax.set_ylabel('Korelasi Pearson', fontsize=9)
    ax.legend(fontsize=8, loc='upper left')
    ax.axhline(y=0, color='gray', linewidth=0.8)

for j in range(n_wilayah, len(axes)):
    fig.delaxes(axes[j])

plt.tight_layout()
plt.savefig(OUTPUT_FOLDER / "grafik_korelasimalang.png", dpi=300, bbox_inches='tight')
plt.show()

print("\nGrafik disimpan ke: analysis/hasil/grafik_korelasimalang.png")