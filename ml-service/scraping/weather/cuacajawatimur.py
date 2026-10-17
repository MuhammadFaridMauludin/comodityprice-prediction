import os
import sys
import time
from datetime import date, datetime, timedelta

import mysql.connector
import pandas as pd
import requests
from dotenv import load_dotenv

# KONFIGURASI

# Lokasi file:
# project-root/
# ├── .env
# └── ml-service/
#     └── scraping/
#         ├── price/          (berisi log_helper.py)
#         └── weather/
#             └── scrape_weather.py

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.abspath(os.path.join(SCRIPT_DIR, "..", ".."))

# log_helper.py dipakai bersama dengan script harga (folder price)
sys.path.insert(0, os.path.join(SCRIPT_DIR, "..", "price"))
from log_helper import catat_log  # noqa: E402

# Membaca file .env di root proyek
load_dotenv(os.path.join(PROJECT_ROOT, ".env"))

# Dipakai hanya jika sebuah titik belum punya data sama sekali
TANGGAL_AWAL_DATA = "2022-01-01"

API_URL = "https://archive-api.open-meteo.com/v1/archive"


# KONEKSI DATABASE
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


# TANGGAL TERAKHIR PER TITIK
def ambil_tanggal_terakhir(mycursor):
    """
    Mengembalikan dict {id_koordinat: tanggal_terakhir (date)}.
    Titik yang belum punya data tidak ada di dict.
    """
    mycursor.execute(
        "SELECT id_koordinat, MAX(tanggal) FROM data_cuaca GROUP BY id_koordinat"
    )

    hasil = {}
    for id_koordinat, tanggal in mycursor.fetchall():
        if tanggal is None:
            continue
        if isinstance(tanggal, datetime):
            tanggal = tanggal.date()
        elif isinstance(tanggal, str):
            tanggal = datetime.strptime(tanggal, "%Y-%m-%d").date()
        hasil[id_koordinat] = tanggal

    return hasil


# MENGAMBIL DATA CUACA OPEN-METEO
def ambil_data_cuaca(latitude, longitude, tgl_mulai, tgl_akhir):
    """
    Mengambil suhu dan curah hujan harian serta kelembapan per jam
    untuk rentang tgl_mulai s.d. tgl_akhir (objek date).
    Kelembapan diagregasi menjadi rata-rata harian.
    """
    params = {
        "latitude": latitude,
        "longitude": longitude,
        "start_date": tgl_mulai.strftime("%Y-%m-%d"),
        "end_date": tgl_akhir.strftime("%Y-%m-%d"),
        "daily": "temperature_2m_mean,precipitation_sum",
        "hourly": "relative_humidity_2m",
        "timezone": "Asia/Jakarta",
    }

    response = requests.get(API_URL, params=params, timeout=120)
    response.raise_for_status()
    data = response.json()

    if data.get("error"):
        raise ValueError(
            data.get("reason", "Open-Meteo mengembalikan kesalahan.")
        )

    # A. Data suhu dan curah hujan per hari
    df_harian = pd.DataFrame(data["daily"])
    df_harian.rename(
        columns={
            "time": "tanggal",
            "temperature_2m_mean": "suhu",
            "precipitation_sum": "curah_hujan",
        },
        inplace=True,
    )
    df_harian["tanggal"] = pd.to_datetime(df_harian["tanggal"]).dt.date

    # B. Data kelembapan per jam
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
        .rename(columns={"kelembapan_jam": "kelembapan"})
    )

    # C. Menggabungkan data berdasarkan tanggal
    df_final = df_harian.merge(df_kelembapan, on="tanggal", how="left")
    df_final = df_final[["tanggal", "suhu", "curah_hujan", "kelembapan"]]

    # Buang hari yang datanya belum lengkap (misalnya hari-hari terakhir
    # yang belum tersedia di arsip). Ini penting: kalau baris kosong ikut
    # disimpan, MAX(tanggal) akan maju melewatinya dan hari itu tidak
    # akan pernah terisi lagi pada scraping berikutnya.
    df_final = df_final.dropna(
        subset=["suhu", "curah_hujan", "kelembapan"], how="any"
    ).reset_index(drop=True)

    return df_final


# MENYIMPAN DATA KE MYSQL
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

    values = [
        (
            int(id_koordinat),
            row.tanggal,
            float(row.suhu),
            float(row.curah_hujan),
            float(row.kelembapan),
        )
        for row in df.itertuples(index=False)
    ]

    if values:
        mycursor.executemany(sql, values)

    return len(values)


# PROGRAM UTAMA
def main():
    mulai = time.time()
    status = "sukses"
    pesan = ""

    # Data diambil sampai KEMARIN (hari ini Senin -> sampai Minggu)
    tgl_akhir = date.today() - timedelta(days=1)
    tgl_awal_default = datetime.strptime(TANGGAL_AWAL_DATA, "%Y-%m-%d").date()
    rentang = f"sampai {tgl_akhir}"

    mydb = None
    mycursor = None

    try:
        print("=" * 60)
        print("SCRAPER DATA CUACA OPEN-METEO")
        print("=" * 60)
        print(f"Data diambil sampai: {tgl_akhir}")

        # Koneksi ada di dalam try supaya kegagalannya ikut tercatat
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
            print("Tidak ada titik koordinat di tabel koordinat_cuaca.")
            status = "peringatan"
            pesan = "Cuaca: tidak ada titik koordinat di tabel koordinat_cuaca"
            return status

        total_titik = len(daftar_koordinat)
        print(f"\nJumlah titik koordinat: {total_titik}")

        # Tanggal terakhir di database untuk semua titik (satu query)
        tanggal_terakhir = ambil_tanggal_terakhir(mycursor)

        titik_berhasil = 0
        total_baris = 0
        titik_terbaru = []   # sudah up-to-date, dilewati
        titik_kosong = []    # API belum punya data lengkap untuk rentang itu
        titik_gagal = []     # error saat ambil/simpan

        # Memproses setiap titik koordinat
        for nomor, koordinat in enumerate(daftar_koordinat, start=1):
            id_koordinat, nama_koordinat, latitude, longitude = koordinat

            print("\n" + "-" * 60)
            print(f"Titik {nomor}/{total_titik}")
            print(f"ID koordinat : {id_koordinat}")
            print(f"Nama lokasi  : {nama_koordinat}")
            print(f"Latitude     : {latitude}")
            print(f"Longitude    : {longitude}")

            # Tentukan rentang untuk titik ini
            terakhir = tanggal_terakhir.get(id_koordinat)
            tgl_mulai = (
                terakhir + timedelta(days=1) if terakhir else tgl_awal_default
            )

            if tgl_mulai > tgl_akhir:
                print(f"Sudah terbaru (data terakhir: {terakhir}).")
                titik_terbaru.append(str(nama_koordinat))
                continue

            print(f"Periode      : {tgl_mulai} sampai {tgl_akhir}")

            try:
                df = ambil_data_cuaca(
                    float(latitude), float(longitude), tgl_mulai, tgl_akhir
                )

                if df.empty:
                    print("Belum ada data lengkap yang tersedia untuk periode ini.")
                    titik_kosong.append(str(nama_koordinat))
                    time.sleep(1)
                    continue

                jumlah = simpan_data(mycursor, id_koordinat, df)

                # Commit per titik agar titik yang berhasil tetap tersimpan
                mydb.commit()

                titik_berhasil += 1
                total_baris += jumlah

                print(f"Data harian diproses: {jumlah} baris")
                print(
                    "Tanggal data: "
                    f"{df['tanggal'].min()} sampai {df['tanggal'].max()}"
                )
                print("Penyimpanan berhasil.")

            except Exception as error:
                mydb.rollback()
                titik_gagal.append(str(nama_koordinat))
                print(f"Gagal memproses {nama_koordinat}: {error}")

            # Jeda antarpermintaan API
            time.sleep(1)

        print("\n" + "=" * 60)
        print("PROSES SCRAPING SELESAI")
        print("=" * 60)

        # Tentukan status akhir untuk log
        if total_titik == len(titik_terbaru):
            pesan = f"Cuaca: data sudah terbaru ({rentang})"

        elif titik_berhasil == 0 and not titik_kosong and not titik_terbaru:
            status = "gagal"
            pesan = (
                f"Cuaca: semua {total_titik} titik gagal ({rentang}): "
                f"{', '.join(titik_gagal)}"
            )

        else:
            bagian = [
                f"{titik_berhasil}/{total_titik} titik tersimpan, "
                f"{total_baris} baris ({rentang})"
            ]
            if titik_terbaru:
                bagian.append(f"{len(titik_terbaru)} titik sudah terbaru")
            if titik_kosong:
                bagian.append(
                    f"data belum tersedia: {', '.join(titik_kosong)}"
                )
            if titik_gagal:
                bagian.append(f"gagal: {', '.join(titik_gagal)}")

            if titik_gagal or titik_kosong:
                status = "peringatan"

            pesan = "Cuaca: " + "; ".join(bagian)

    except Exception as error:
        status = "gagal"
        pesan = f"Cuaca: {type(error).__name__}: {error}"
        print(f"\nProses gagal: {error}")

    finally:
        durasi = time.time() - mulai

        if mycursor is not None:
            mycursor.close()

        if mydb is not None:
            catat_log(mydb, "Scraping Cuaca", "Open-Meteo", status, durasi, pesan[:500])
        else:
            print(f"Log tidak dapat ditulis ke database (koneksi gagal): {pesan}")

        if mydb is not None and mydb.is_connected():
            mydb.close()
            print("Koneksi database ditutup.")

    return status


if __name__ == "__main__":
    hasil = main()
    # Kode keluar 1 jika gagal, supaya FastAPI bisa mendeteksinya
    sys.exit(1 if hasil == "gagal" else 0)