import time
from datetime import datetime, timedelta
import os
from dotenv import load_dotenv
import mysql.connector
import pandas as pd
import requests
from bs4 import BeautifulSoup

# KONFIGURASI
URL = "https://siskaperbapo.jatimprov.go.id/harga-komoditas"

NAMA_KOMODITAS = "Cabe Rawit Merah"
ID_KOMODITAS = 1
ID_WILAYAH = 37
NAMA_WILAYAH = "Kota Surabaya"

TANGGAL_AKHIR = datetime.strptime("2026-10-01", "%Y-%m-%d")
TANGGAL_MULAI = datetime.strptime("2022-01-01", "%Y-%m-%d")

# Koneksi database
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.abspath(
    os.path.join(SCRIPT_DIR, "..", "..")
)

load_dotenv(os.path.join(PROJECT_ROOT, ".env"))

mydb = mysql.connector.connect(
    host=os.getenv("DB_HOST"),
    user=os.getenv("DB_USER"),
    password=os.getenv("DB_PASSWORD", ""),
    database=os.getenv("DB_NAME")
)

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
        raise RuntimeError(
            "Token atau ID komoditas tidak ditemukan."
        )

    print(f"Komoditas: {NAMA_KOMODITAS}")
    print(f"ID komoditas Siskaperbapo: {id_komoditas_web}")

    semua_data = []
    tanggal_sekarang = TANGGAL_AKHIR

    print(
        f"\nScraping harga {NAMA_KOMODITAS} "
        f"untuk {NAMA_WILAYAH}"
    )
    print(
        f"Periode: {TANGGAL_MULAI.date()} "
        f"sampai {TANGGAL_AKHIR.date()}\n"
    )

    while tanggal_sekarang >= TANGGAL_MULAI:
        tanggal_request = tanggal_sekarang.strftime("%Y-%m-%d")

        payload = {
            "csrf_token": csrf_token,
            "tanggal_akhir": tanggal_request,
            "komoditas": id_komoditas_web,
        }

        try:
            response = session.post(
                URL,
                data=payload,
                timeout=15
            )
            response.raise_for_status()

            soup = BeautifulSoup(response.text, "html.parser")
            table = soup.find("table")

            if not table:
                print(f"[{tanggal_request}] Tabel tidak ditemukan.")
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
                cells = [
                    cell.get_text(strip=True)
                    for cell in row.find_all("td")
                ]

                if len(cells) <= 1:
                    continue

                nama = cells[1]

                if nama.startswith(("Kota", "Kabupaten")):
                    daerah = nama
                    continue

                if "Pasar" in nama and daerah == NAMA_WILAYAH:
                    for tanggal, harga in zip(header, cells[2:]):
                        harga_bersih = (
                            harga.replace(".", "")
                            .replace("-", "")
                            .strip()
                        )

                        if not harga_bersih.isdigit():
                            continue

                        tanggal_iso = pd.to_datetime(
                            tanggal,
                            errors="coerce"
                        )

                        if pd.isna(tanggal_iso):
                            continue

                        tanggal_iso = tanggal_iso.strftime("%Y-%m-%d")

                        if not (
                            TANGGAL_MULAI.strftime("%Y-%m-%d")
                            <= tanggal_iso
                            <= TANGGAL_AKHIR.strftime("%Y-%m-%d")
                        ):
                            continue

                        semua_data.append({
                            "tanggal": tanggal_iso,
                            "daerah": daerah,
                            "pasar": nama,
                            "harga": int(harga_bersih),
                        })

                        jumlah += 1

            print(
                f"[{tanggal_request}] "
                f"{jumlah} data harga ditemukan."
            )

        except requests.RequestException as error:
            print(f"[{tanggal_request}] Error HTTP: {error}")

        tanggal_sekarang -= timedelta(days=7)
        time.sleep(0.3)

    return semua_data

# PEMBERSIHAN DATA
def bersihkan_data(semua_data):
    if not semua_data:
        return pd.DataFrame(
            columns=["tanggal", "harga"]
        )

    df = pd.DataFrame(semua_data)

    # Hindari data pasar yang sama tercatat berulang
    df = df.drop_duplicates(
        subset=["tanggal", "daerah", "pasar"]
    )

    # Satu harga rata-rata per tanggal untuk Surabaya
    df = (
        df.groupby("tanggal", as_index=False)["harga"]
        .mean()
    )

    df["harga"] = df["harga"].round(0).astype(int)

    df["tanggal"] = pd.to_datetime(
        df["tanggal"]
    ).dt.date

    return df

# SIMPAN KE MYSQL
def simpan_ke_database(df):
    if df.empty:
        print("Tidak ada data untuk disimpan.")
        return

    sql = """
        INSERT INTO data_harga
            (id_komoditas, id_wilayah, tanggal, harga)
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            harga = VALUES(harga)
    """

    data_insert = [
        (
            ID_KOMODITAS,
            ID_WILAYAH,
            baris.tanggal,
            baris.harga,
        )
        for baris in df.itertuples(index=False)
    ]

    cursor = mydb.cursor()

    try:
        cursor.executemany(sql, data_insert)
        mydb.commit()

        print(f"\nJumlah tanggal diproses: {len(data_insert)}")
        print(f"Jumlah perubahan menurut MySQL: {cursor.rowcount}")
        print("Penyimpanan data harga selesai.")

    except mysql.connector.Error:
        mydb.rollback()
        raise

    finally:
        cursor.close()

# PROGRAM UTAMA
try:
    semua_data = scraping_harga()
    df_harga = bersihkan_data(semua_data)

    print("\nContoh data hasil pembersihan:")
    print(df_harga.head(10))
    print(f"\nTotal tanggal unik: {len(df_harga)}")

    simpan_ke_database(df_harga)

except Exception as error:
    print(f"\nProses gagal: {error}")

finally:
    session.close()

    if mydb.is_connected():
        mydb.close()
