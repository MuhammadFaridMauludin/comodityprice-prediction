"""
Dokumentasi: https://open-meteo.com/en/docs/historical-weather-api
"""
import os
import requests
import pandas as pd
from pathlib import Path

LATITUDE = -7.82
LONGITUDE = 112.0
START_DATE = "2024-01-01"
END_DATE = "2026-09-03"

BASE_URL = "https://archive-api.open-meteo.com/v1/archive"

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent

output_folder = PROJECT_ROOT / "data" / "weather"
output_path = output_folder / "sp-kediri.csv"

def data_cuaca_harian():
    print("Sedang memuat data suhu & curah hujan harian dari Open-Meteo...")

    params = {
        "latitude": LATITUDE,
        "longitude": LONGITUDE,
        "start_date": START_DATE,
        "end_date": END_DATE,
        "daily": ",".join([
            "temperature_2m_mean",
            "precipitation_sum",
        ]),
        "timezone": "Asia/Jakarta",
    }

    response = requests.get(BASE_URL, params=params)
    response.raise_for_status()
    data = response.json()

    df = pd.DataFrame(data["daily"])
    df.rename(columns={
        "time": "tanggal",
        "temperature_2m_mean": "suhu",
        "precipitation_sum": "curah_hujan"
    }, inplace=True)

    print(f"-> Selesai. {len(df)} baris data harian berhasil diambil "
          f"({df['tanggal'].iloc[0]} s/d {df['tanggal'].iloc[-1]}).")
    return df

def data_kelembapan():
    print("Sedang memuat data kelembapan per jam dari Open-Meteo...")

    params = {
        "latitude": LATITUDE,
        "longitude": LONGITUDE,
        "start_date": START_DATE,
        "end_date": END_DATE,
        "hourly": "relative_humidity_2m",
        "timezone": "Asia/Jakarta",
    }

    response = requests.get(BASE_URL, params=params)
    response.raise_for_status()
    data = response.json()

    df_hourly = pd.DataFrame(data["hourly"])
    print(f"-> Data per jam diterima: {len(df_hourly)} baris.")

    df_hourly.rename(columns={"time": "waktu"}, inplace=True)
    df_hourly["waktu"] = pd.to_datetime(df_hourly["waktu"])
    df_hourly["tanggal"] = df_hourly["waktu"].dt.date

    print("Sedang mengagregasi data per jam menjadi rata-rata harian...")
    df_harian = (
        df_hourly.groupby("tanggal")["relative_humidity_2m"]
        .mean()
        .round(2)
        .reset_index()
        .rename(columns={"relative_humidity_2m": "kelembapan"})
    )

    print(f"-> Selesai. Kelembapan harian: {len(df_harian)} baris.")
    return df_harian

#cek folder
output_folder = "../../data/weather"
output_path = os.path.join(output_folder, "cuaca_kediri.csv")

print(f"Memeriksa folder output '{output_folder}'...")
os.makedirs(output_folder, exist_ok=True)
print("Folder ada?", os.path.exists(output_folder))
print("Bisa tulis?", os.access(output_folder, os.W_OK))


if __name__ == "__main__":
    print("=" * 50)
    print("MULAI PROSES PENGAMBILAN DATA CUACA")
    print("=" * 50)

    df_cuaca = data_cuaca_harian()
    print("\nCuplikan data suhu & curah hujan:")
    print(df_cuaca.head())

    print("\n" + "-" * 50)

    df_kelembapan = data_kelembapan()
    print("\nCuplikan data kelembapan:")
    print(df_kelembapan.head())

    print("\n" + "-" * 50)
    print("Sedang menggabungkan data suhu/curah hujan dengan kelembapan...")

    df_cuaca["tanggal"] = pd.to_datetime(df_cuaca["tanggal"]).dt.date
    df_final = df_cuaca.merge(df_kelembapan, on="tanggal", how="left")

    print(f"-> Data gabungan: {len(df_final)} baris, {df_final.shape[1]} kolom.")

    print(f"\nSedang menyimpan data ke '{output_path}'...")
    df_final.to_csv(output_path, index=False)

    print("=" * 50)
    print(f"SELESAI. Data berhasil disimpan ke '{output_path}'")
    print("=" * 50)
    print("\nCuplikan hasil akhir:")
    print(df_final.head())