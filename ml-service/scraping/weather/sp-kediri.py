import os
import time
import requests
import pandas as pd
import mysql.connector

from pathlib import Path
from dotenv import load_dotenv


# =========================================================
# 1. KONFIGURASI
# =========================================================

# Lokasi file:
# project-root/
# ├── .env
# └── ml-service/
#     └── scraping/
#         └── weather/
#             └── scrape_weather.py

# Koneksi database
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.abspath(
    os.path.join(SCRIPT_DIR, "..", "..")
)
# Membaca file .env di root proyek
load_dotenv(os.path.join(PROJECT_ROOT, ".env"))

START_DATE = "2026-09-20"
END_DATE = "2026-10-12"

API_URL = "https://archive-api.open-meteo.com/v1/archive"


# =========================================================
# 2. KONEKSI DATABASE
# =========================================================

def koneksi_database():
    """Membuat koneksi ke MySQL menggunakan konfigurasi .env."""

    db_host = os.getenv("DB_HOST")
    db_user = os.getenv("DB_USER")
    db_password = os.getenv("DB_PASSWORD", "")
    db_name = os.getenv("DB_NAME")

    if not db_host or not db_user or not db_name:
        raise ValueError(
            "Konfigurasi database tidak lengkap. "
            "Pastikan DB_HOST, DB_USER, dan DB_NAME "
            "tersedia di file .env."
        )

    mydb = mysql.connector.connect(
        host=db_host,
        user=db_user,
        password=db_password,
        database=db_name,
    )

    print("Koneksi database berhasil.")
    return mydb


# =========================================================
# 3. MENGAMBIL DATA CUACA OPEN-METEO
# =========================================================

def ambil_data_cuaca(latitude, longitude):
    """
    Mengambil suhu dan curah hujan harian serta kelembapan per jam.
    Kelembapan kemudian diagregasi menjadi rata-rata harian.
    """

    params = {
        "latitude": latitude,
        "longitude": longitude,
        "start_date": START_DATE,
        "end_date": END_DATE,
        "daily": "temperature_2m_mean,precipitation_sum",
        "hourly": "relative_humidity_2m",
        "timezone": "Asia/Jakarta",
    }

    response = requests.get(
        API_URL,
        params=params,
        timeout=120,
    )

    response.raise_for_status()
    data = response.json()

    if data.get("error"):
        raise ValueError(
            data.get("reason", "Open-Meteo mengembalikan kesalahan.")
        )

    # ---------------------------------------------
    # A. Data suhu dan curah hujan per hari
    # ---------------------------------------------

    df_harian = pd.DataFrame(data["daily"])

    df_harian.rename(
        columns={
            "time": "tanggal",
            "temperature_2m_mean": "suhu",
            "precipitation_sum": "curah_hujan",
        },
        inplace=True,
    )

    df_harian["tanggal"] = pd.to_datetime(
        df_harian["tanggal"]
    ).dt.date

    # ---------------------------------------------
    # B. Data kelembapan per jam
    # ---------------------------------------------

    df_jam = pd.DataFrame(data["hourly"])

    df_jam.rename(
        columns={
            "time": "waktu",
            "relative_humidity_2m": "kelembapan_jam",
        },
        inplace=True,
    )

    df_jam["waktu"] = pd.to_datetime(df_jam["waktu"])
    df_jam["tanggal"] = df_jam["waktu"].dt.date

    # Rata-rata kelembapan per tanggal
    df_kelembapan = (
        df_jam.groupby("tanggal")["kelembapan_jam"]
        .mean()
        .round(2)
        .reset_index()
        .rename(
            columns={
                "kelembapan_jam": "kelembapan",
            }
        )
    )

    # ---------------------------------------------
    # C. Menggabungkan data berdasarkan tanggal
    # ---------------------------------------------

    df_final = df_harian.merge(
        df_kelembapan,
        on="tanggal",
        how="left",
    )

    # Urutan kolom
    df_final = df_final[
        ["tanggal", "suhu", "curah_hujan", "kelembapan"]
    ]

    return df_final


# =========================================================
# 4. MENYIMPAN DATA KE MYSQL
# =========================================================

def simpan_data(mycursor, id_koordinat, df):
    """
    Menyimpan data ke data_cuaca.

    Jika kombinasi id_koordinat dan tanggal sudah ada,
    data akan diperbarui, bukan diduplikasi.
    """

    sql = """
        INSERT INTO data_cuaca
        (
            id_koordinat,
            tanggal,
            suhu,
            curah_hujan,
            kelembapan
        )
        VALUES (%s, %s, %s, %s, %s)

        ON DUPLICATE KEY UPDATE
            suhu = VALUES(suhu),
            curah_hujan = VALUES(curah_hujan),
            kelembapan = VALUES(kelembapan)
    """

    values = []

    for row in df.itertuples(index=False):
        suhu = (
            None
            if pd.isna(row.suhu)
            else float(row.suhu)
        )

        curah_hujan = (
            None
            if pd.isna(row.curah_hujan)
            else float(row.curah_hujan)
        )

        kelembapan = (
            None
            if pd.isna(row.kelembapan)
            else float(row.kelembapan)
        )

        values.append(
            (
                int(id_koordinat),
                row.tanggal,
                suhu,
                curah_hujan,
                kelembapan,
            )
        )

    if values:
        mycursor.executemany(sql, values)

    return len(values)


# =========================================================
# 5. PROSES UTAMA
# =========================================================

def main():
    mydb = None
    mycursor = None

    try:
        print("=" * 60)
        print("SCRAPER DATA CUACA OPEN-METEO")
        print("=" * 60)

        print(f"Periode awal : {START_DATE}")
        print(f"Periode akhir: {END_DATE}")

        # Membuka koneksi database
        mydb = koneksi_database()
        mycursor = mydb.cursor()

        # Mengambil seluruh titik koordinat yang tersimpan
        mycursor.execute(
            """
            SELECT
                id_koordinat,
                nama_koordinat,
                latitude,
                longitude
            FROM koordinat_cuaca
            ORDER BY id_koordinat
            """
        )

        daftar_koordinat = mycursor.fetchall()

        if not daftar_koordinat:
            print(
                "Tidak ada titik koordinat "
                "di tabel koordinat_cuaca."
            )
            return

        total_titik = len(daftar_koordinat)

        print(f"\nJumlah titik koordinat: {total_titik}")

        # Memproses setiap titik koordinat
        for nomor, koordinat in enumerate(
            daftar_koordinat,
            start=1,
        ):
            (
                id_koordinat,
                nama_koordinat,
                latitude,
                longitude,
            ) = koordinat

            print("\n" + "-" * 60)
            print(f"Titik {nomor}/{total_titik}")
            print(f"ID koordinat : {id_koordinat}")
            print(f"Nama lokasi  : {nama_koordinat}")
            print(f"Latitude     : {latitude}")
            print(f"Longitude    : {longitude}")

            try:
                # Mengambil data dari Open-Meteo
                df = ambil_data_cuaca(
                    float(latitude),
                    float(longitude),
                )

                if df.empty:
                    print("Tidak ada data cuaca yang diterima.")
                    continue

                # Menyimpan data ke MySQL
                jumlah = simpan_data(
                    mycursor,
                    id_koordinat,
                    df,
                )

                # Commit per titik agar hasil titik yang
                # berhasil tetap tersimpan.
                mydb.commit()

                print(f"Data harian diproses: {jumlah} baris")
                print(
                    "Tanggal data: "
                    f"{df['tanggal'].min()} sampai "
                    f"{df['tanggal'].max()}"
                )

                print("Penyimpanan berhasil.")

            except (
                requests.RequestException,
                ValueError,
                KeyError,
                mysql.connector.Error,
            ) as error:
                mydb.rollback()

                print(
                    f"Gagal memproses {nama_koordinat}: "
                    f"{error}"
                )

            except Exception as error:
                mydb.rollback()

                print(
                    f"Kesalahan tidak terduga pada "
                    f"{nama_koordinat}: {error}"
                )

            # Jeda antarpermintaan API
            time.sleep(1)

        print("\n" + "=" * 60)
        print("PROSES SCRAPING SELESAI")
        print("=" * 60)

    except (
        mysql.connector.Error,
        ValueError,
    ) as error:
        print(f"Proses gagal: {error}")

    finally:
        if mycursor is not None:
            mycursor.close()

        if mydb is not None and mydb.is_connected():
            mydb.close()
            print("Koneksi database ditutup.")


# =========================================================
# 6. EKSEKUSI PROGRAM
# =========================================================

if __name__ == "__main__":
    main()
