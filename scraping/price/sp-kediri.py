import os
import time
from pathlib import Path
from datetime import datetime, timedelta
import pandas as pd
import requests
from bs4 import BeautifulSoup

URL = "https://siskaperbapo.jatimprov.go.id/harga-komoditas"
NAMA_KOMODITAS = "Cabe Rawit Merah"

TANGGAL_AKHIR = datetime.strptime("2026-09-03", "%Y-%m-%d")
TANGGAL_MULAI = datetime.strptime("2024-01-01", "%Y-%m-%d")

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent.parent

output_folder = PROJECT_ROOT / "data" / "price"
output_path = output_folder / "harga-cabai.csv"

session = requests.Session()
session.headers.update({
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "X-Requested-With": "XMLHttpRequest",
    "Referer": URL,
})

def ambil_token_dan_komoditas():
    """Ambil csrf_token segar + value id komoditas dari halaman awal."""
    res = session.get(URL)
    soup = BeautifulSoup(res.text, "html.parser")

    token_input = soup.find("input", {"name": "csrf_token"})
    if not token_input:
        meta = soup.find("meta", {"name": "csrf-token"})
        token = meta["content"] if meta else None
    else:
        token = token_input.get("value")

    val_komoditas = None
    select_elem = soup.find("select", {"id": "komoditas"}) or soup.find("select", {"name": "komoditas"})

    print("\nOpsi komoditas yang tersedia (untuk verifikasi):")
    if select_elem:
        for opt in select_elem.find_all("option"):
            teks_opsi = opt.get_text(strip=True)
            print(f"  value={opt.get('value')!r} -> {teks_opsi!r}")
            if NAMA_KOMODITAS.lower() in teks_opsi.lower() and val_komoditas is None:
                val_komoditas = opt.get("value")

    if val_komoditas:
        print(f"\n-> Dipilih otomatis: value='{val_komoditas}' (match dengan '{NAMA_KOMODITAS}')")
    else:
        print(f"\n-> PERINGATAN: tidak ada opsi yang match dengan '{NAMA_KOMODITAS}'. "
              "Cek daftar di atas dan sesuaikan NAMA_KOMODITAS.")

    return token, val_komoditas


print("Mengambil token & id komoditas...")
csrf_token, val_komoditas = ambil_token_dan_komoditas()

if not csrf_token or not val_komoditas:
    print("GAGAL ambil token/komoditas, cek struktur HTML halaman awal atau NAMA_KOMODITAS.")
    exit()

all_data = []
curr_date = TANGGAL_AKHIR

print(f"\nMulai scraping mundur dari {TANGGAL_AKHIR.date()} ke {TANGGAL_MULAI.date()}...\n")

while curr_date >= TANGGAL_MULAI:
    str_tanggal = curr_date.strftime("%Y-%m-%d")

    payload = {
        "csrf_token": csrf_token,
        "tanggal_akhir": str_tanggal,
        "komoditas": val_komoditas,
    }

    try:
        res = session.post(URL, data=payload, timeout=15)
        soup = BeautifulSoup(res.text, "html.parser")
        table = soup.find("table")

        if table:
            header = [h.get_text(strip=True) for h in table.find_all("th") if "-" in h.get_text(strip=True)]
            daerah = None
            count = 0

            for row in table.find_all("tr"):
                isi = [x.get_text(strip=True) for x in row.find_all("td")]
                if not isi or len(isi) <= 1:
                    continue
                nama = isi[1]
                if nama.startswith("Kota") or nama.startswith("Kabupaten"):
                    daerah = nama
                    continue
                if "Pasar" in nama and daerah:
                    for tgl, harga in zip(header, isi[2:]):
                        clean_harga = harga.replace(".", "").replace("-", "").strip()
                        if clean_harga.isdigit():
                            all_data.append({
                                "tanggal": tgl,
                                "daerah": daerah,
                                "pasar": nama,
                                "harga": int(clean_harga)
                            })
                            count += 1

            if header:
                print(f"[{str_tanggal}] Periode: {header[0]} s/d {header[-1]} ({count} baris)")
            else:
                print(f"[{str_tanggal}] Tabel ada tapi tanpa header tanggal (kemungkinan token expired?)")
        else:
            print(f"[{str_tanggal}] Tabel tidak ditemukan.")

    except Exception as e:
        print(f"[{str_tanggal}] Error: {e}")

    curr_date -= timedelta(days=7)
    time.sleep(0.3)

if all_data:
    df = pd.DataFrame(all_data)
    df = df[df["tanggal"] >= TANGGAL_MULAI.strftime("%Y-%m-%d")]
    df = df.drop_duplicates(subset=["tanggal", "daerah", "pasar"])
    df_rata = df.groupby(["tanggal", "daerah"])["harga"].mean().reset_index()
    df_pivot = df_rata.pivot(index="tanggal", columns="daerah", values="harga").reset_index().round(0)

    output_folder.mkdir(parents=True, exist_ok=True)
    df_pivot.to_csv(output_path, index=False)

    print(f"\n[SUKSES] {len(df_pivot)} hari unik tersimpan ke {output_path}")
else:
    print("\nData gagal diproses.")