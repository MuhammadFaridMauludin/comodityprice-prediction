import os
import time
from datetime import timedelta

import mysql.connector
import pandas as pd
import requests
from bs4 import BeautifulSoup
from dotenv import load_dotenv

from log_helper import catat_log, tentukan_rentang

# KONFIGURASI
URL = "https://siskaperbapo.jatimprov.go.id/harga-komoditas"

NAMA_KOMODITAS = "Bawang Merah"
ID_KOMODITAS = 3
ID_WILAYAH = 37
NAMA_WILAYAH = "Kota Surabaya"

TANGGAL_AWAL_DATA = "2022-01-01"

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.abspath(os.path.join(SCRIPT_DIR, "..", ".."))
load_dotenv(os.path.join(PROJECT_ROOT, ".env"))

# Diisi di blok utama
mydb = None
TANGGAL_MULAI = None
TANGGAL_AKHIR = None

session = requests.Session()
session.headers.update({
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/120.0.0.0 Safari/537.36"
    ),
    "X-Requested-With": "XMLHttpRequest",
    "Referer": URL,
})


# AMBIL TOKEN DAN KOMODITAS
def ambil_token_dan_komoditas():
    response = session.get(URL, timeout=15)
    response.raise_for_status()

    soup = BeautifulSoup(response.text, "html.parser")

    token_input = soup.find("input", {"name": "csrf_token"})
    if token_input:
        token = token_input.get("value")
    else:
        meta = soup.find("meta", {"name": "csrf-token"})
        token = meta.get("content") if meta else None

    select_elem = (
        soup.find("select", {"id": "komoditas"})
        or soup.find("select", {"name": "komoditas"})
    )

    id_komoditas_web = None
    if select_elem:
        for option in select_elem.find_all("option"):
            nama = option.get_text(strip=True)
            if NAMA_KOMODITAS.lower() in nama.lower():
                id_komoditas_web = option.get("value")
                break

    return token, id_komoditas_web


# SCRAPING HARGA
def scraping_harga():
    print("Mengambil token dan ID komoditas...")

    csrf_token, id_komoditas_web = ambil_token_dan_komoditas()

    if not csrf_token or not id_komoditas_web:
        raise RuntimeError("Token atau ID komoditas tidak ditemukan.")

    print(f"Komoditas: {NAMA_KOMODITAS}")
    print(f"ID komoditas Siskaperbapo: {id_komoditas_web}")

    semua_data = []
    tanggal_sekarang = TANGGAL_AKHIR

    jumlah_request = 0
    jumlah_error = 0

    print(f"\nScraping harga {NAMA_KOMODITAS} untuk {NAMA_WILAYAH}")
    print(f"Periode: {TANGGAL_MULAI} sampai {TANGGAL_AKHIR}\n")

    batas_awal = TANGGAL_MULAI.strftime("%Y-%m-%d")
    batas_akhir = TANGGAL_AKHIR.strftime("%Y-%m-%d")

    while tanggal_sekarang >= TANGGAL_MULAI:
        tanggal_request = tanggal_sekarang.strftime("%Y-%m-%d")

        payload = {
            "csrf_token": csrf_token,
            "tanggal_akhir": tanggal_request,
            "komoditas": id_komoditas_web,
        }

        jumlah_request += 1

        try:
            response = session.post(URL, data=payload, timeout=15)
            response.raise_for_status()

            soup = BeautifulSoup(response.text, "html.parser")
            table = soup.find("table")

            if not table:
                print(f"[{tanggal_request}] Tabel tidak ditemukan.")
                jumlah_error += 1
                tanggal_sekarang -= timedelta(days=7)
                time.sleep(0.3)
                continue

            header = [
                th.get_text(strip=True)
                for th in table.find_all("th")
                if "-" in th.get_text(strip=True)
            ]

            daerah = None
            jumlah = 0

            for row in table.find_all("tr"):
                cells = [cell.get_text(strip=True) for cell in row.find_all("td")]

                if len(cells) <= 1:
                    continue

                nama = cells[1]

                if nama.startswith(("Kota", "Kabupaten")):
                    daerah = nama
                    continue

                if "Pasar" in nama and daerah == NAMA_WILAYAH:
                    for tanggal, harga in zip(header, cells[2:]):
                        harga_bersih = (
                            harga.replace(".", "").replace("-", "").strip()
                        )

                        if not harga_bersih.isdigit():
                            continue

                        tanggal_iso = pd.to_datetime(tanggal, errors="coerce")
                        if pd.isna(tanggal_iso):
                            continue

                        tanggal_iso = tanggal_iso.strftime("%Y-%m-%d")

                        if not (batas_awal <= tanggal_iso <= batas_akhir):
                            continue

                        semua_data.append({
                            "tanggal": tanggal_iso,
                            "daerah": daerah,
                            "pasar": nama,
                            "harga": int(harga_bersih),
                        })
                        jumlah += 1

            print(f"[{tanggal_request}] {jumlah} data harga ditemukan.")

        except requests.RequestException as error:
            print(f"[{tanggal_request}] Error HTTP: {error}")
            jumlah_error += 1

        tanggal_sekarang -= timedelta(days=7)
        time.sleep(0.3)

    return semua_data, jumlah_request, jumlah_error


# PEMBERSIHAN DATA
def bersihkan_data(semua_data):
    if not semua_data:
        return pd.DataFrame(columns=["tanggal", "harga"])

    df = pd.DataFrame(semua_data)

    # Hindari data pasar yang sama tercatat berulang
    df = df.drop_duplicates(subset=["tanggal", "daerah", "pasar"])

    # Satu harga rata-rata per tanggal untuk Surabaya
    df = df.groupby("tanggal", as_index=False)["harga"].mean()

    df["harga"] = df["harga"].round(0).astype(int)
    df["tanggal"] = pd.to_datetime(df["tanggal"]).dt.date

    return df


# SIMPAN KE MYSQL
def simpan_ke_database(df):
    if df.empty:
        print("Tidak ada data untuk disimpan.")
        return 0

    sql = """
        INSERT INTO data_harga
            (id_komoditas, id_wilayah, tanggal, harga)
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            harga = VALUES(harga)
    """

    data_insert = [
        (ID_KOMODITAS, ID_WILAYAH, baris.tanggal, baris.harga)
        for baris in df.itertuples(index=False)
    ]

    cursor = mydb.cursor()
    try:
        cursor.executemany(sql, data_insert)
        mydb.commit()

        print(f"\nJumlah tanggal diproses: {len(data_insert)}")
        print(f"Jumlah perubahan menurut MySQL: {cursor.rowcount}")
        print("Penyimpanan data harga selesai.")

        return len(data_insert)
    except mysql.connector.Error:
        mydb.rollback()
        raise
    finally:
        cursor.close()


# PROGRAM UTAMA
mulai = time.time()
status = "sukses"
pesan = ""
rentang = "-"

try:
    # Koneksi dan penentuan rentang ada DI DALAM try,
    # supaya kegagalannya ikut tercatat di log.
    mydb = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASSWORD", ""),
        database=os.getenv("DB_NAME"),
    )

    TANGGAL_MULAI, TANGGAL_AKHIR = tentukan_rentang(
        mydb, ID_KOMODITAS, ID_WILAYAH, TANGGAL_AWAL_DATA
    )

    if TANGGAL_MULAI is None or TANGGAL_AKHIR is None:
        pesan = f"{NAMA_KOMODITAS}: data sudah terbaru (up-to-date)"
    else:
        rentang = f"{TANGGAL_MULAI} s.d. {TANGGAL_AKHIR}"

        semua_data, jumlah_request, jumlah_error = scraping_harga()
        df_harga = bersihkan_data(semua_data)

        print("\nContoh data hasil pembersihan:")
        print(df_harga.head(10))
        print(f"\nTotal tanggal unik: {len(df_harga)}")

        jumlah_disimpan = simpan_ke_database(df_harga)

        if jumlah_request > 0 and jumlah_error == jumlah_request:
            status = "gagal"
            pesan = (
                f"{NAMA_KOMODITAS}: semua {jumlah_request} request gagal ({rentang})"
            )
        elif df_harga.empty:
            status = "peringatan"
            pesan = f"{NAMA_KOMODITAS}: tidak ada data harga untuk {rentang}"
        elif jumlah_error > 0:
            status = "peringatan"
            pesan = (
                f"{NAMA_KOMODITAS}: {jumlah_disimpan} data harga tersimpan ({rentang}), "
                f"{jumlah_error} dari {jumlah_request} request gagal"
            )
        else:
            pesan = f"{NAMA_KOMODITAS}: {jumlah_disimpan} data harga tersimpan ({rentang})"

except Exception as error:
    status = "gagal"
    pesan = f"{NAMA_KOMODITAS}: {type(error).__name__}: {error}"
    print(f"\nProses gagal: {error}")

finally:
    durasi = time.time() - mulai

    if mydb is not None:
        catat_log(mydb, "Scraping Harga", "Siskaperbapo", status, durasi, pesan[:500])
    else:
        print(f"Log tidak dapat ditulis ke database (koneksi gagal): {pesan}")

    session.close()

    if mydb is not None and mydb.is_connected():
        mydb.close()