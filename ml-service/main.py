import os
import subprocess
import sys
from typing import Optional

from dotenv import load_dotenv
from fastapi import BackgroundTasks, FastAPI, Header, HTTPException

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
load_dotenv(os.path.join(BASE_DIR, ".env"))

# 2. Ambil nilai key
API_KEY = os.getenv("ML_SERVICE_KEY")
PRICE_DIR = os.path.join(BASE_DIR, "scraping", "price")

# 3. Cetak untuk memastikan key berhasil terbaca saat server dinyalakan
print(f"[DEBUG] API Key Python terbaca: '{API_KEY}'")
# nama komoditas -> file script
SCRIPTS = {
    "bawang-merah": "bawangmerah.py",
    "cabai-merah-keriting": "cabemerahkeriting.py",
    "cabai-rawit-merah": "caberawitmerah.py",
}

app = FastAPI()
state = {"berjalan": False}


def cek_key(x_api_key):
    if not API_KEY or x_api_key != API_KEY:
        raise HTTPException(status_code=401, detail="API key salah")


def worker(daftar):
    try:
        for nama in daftar:
            # Satu proses per komoditas; tiap script mencatat log-nya sendiri
            subprocess.run(
                [sys.executable, SCRIPTS[nama]],
                cwd=PRICE_DIR,
            )
    finally:
        state["berjalan"] = False


@app.post("/scrape/harga", status_code=202)
def scrape_harga(
    background: BackgroundTasks,
    komoditas: Optional[str] = None,        # contoh: ?komoditas=bawang-merah ; kosong = semua
    x_api_key: str = Header(None),
):
    cek_key(x_api_key)

    if state["berjalan"]:
        raise HTTPException(status_code=409, detail="Scraping masih berjalan")

    if komoditas is None:
        daftar = list(SCRIPTS.keys())
    elif komoditas in SCRIPTS:
        daftar = [komoditas]
    else:
        raise HTTPException(status_code=400, detail="Komoditas tidak dikenal")

    state["berjalan"] = True
    background.add_task(worker, daftar)
    return {"message": "Scraping dimulai", "komoditas": daftar}


@app.get("/scrape/harga/status")
def status_scrape(x_api_key: str = Header(None)):
    cek_key(x_api_key)
    return state