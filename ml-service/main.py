import os
import subprocess
import sys
from typing import Optional

from dotenv import load_dotenv
from fastapi import BackgroundTasks, FastAPI, Header, HTTPException

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
load_dotenv(os.path.join(BASE_DIR, ".env"))

# Ambil key
API_KEY = os.getenv("ML_SERVICE_KEY")

PRICE_DIR = os.path.join(BASE_DIR, "scraping", "price")
WEATHER_DIR = os.path.join(BASE_DIR, "scraping", "weather")

# nama komoditas -> file script
SCRIPTS = {
    "bawang-merah": "bawangmerah.py",
    "cabai-merah-keriting": "cabemerahkeriting.py",
    "cabai-rawit-merah": "caberawitmerah.py",
}

# Cuaca hanya satu script
WEATHER_SCRIPT = "cuacajawatimur.py"

app = FastAPI()

# State terpisah, jadi scraping harga dan cuaca bisa jalan bersamaan
state_harga = {"berjalan": False}
state_cuaca = {"berjalan": False}


def cek_key(x_api_key):
    if not API_KEY or x_api_key != API_KEY:
        raise HTTPException(status_code=401, detail="API key salah")


def jalankan_script(state, folder, daftar_script):
    """Jalankan script satu per satu. Tiap script mencatat log-nya sendiri."""
    try:
        for nama_file in daftar_script:
            hasil = subprocess.run(
                [sys.executable, nama_file],
                cwd=folder,
            )
            if hasil.returncode != 0:
                print(f"[PERINGATAN] {nama_file} selesai dengan kode {hasil.returncode}")
    finally:
        state["berjalan"] = False


# HARGA
@app.post("/scrape/harga", status_code=202)
def scrape_harga(
    background: BackgroundTasks,
    komoditas: Optional[str] = None,
    x_api_key: str = Header(None),
):
    cek_key(x_api_key)

    if state_harga["berjalan"]:
        raise HTTPException(status_code=409, detail="Scraping harga masih berjalan")

    if komoditas is None:
        daftar = list(SCRIPTS.keys())
    elif komoditas in SCRIPTS:
        daftar = [komoditas]
    else:
        raise HTTPException(status_code=400, detail="Komoditas tidak dikenal")

    state_harga["berjalan"] = True
    background.add_task(
        jalankan_script, state_harga, PRICE_DIR, [SCRIPTS[n] for n in daftar]
    )
    return {"message": "Scraping harga dimulai", "komoditas": daftar}


@app.get("/scrape/harga/status")
def status_scrape_harga(x_api_key: str = Header(None)):
    cek_key(x_api_key)
    return state_harga


# CUACA
@app.post("/scrape/cuaca", status_code=202)
def scrape_cuaca(
    background: BackgroundTasks,
    x_api_key: str = Header(None),
):
    cek_key(x_api_key)

    if state_cuaca["berjalan"]:
        raise HTTPException(status_code=409, detail="Scraping cuaca masih berjalan")

    state_cuaca["berjalan"] = True
    background.add_task(jalankan_script, state_cuaca, WEATHER_DIR, [WEATHER_SCRIPT])
    return {"message": "Scraping cuaca dimulai"}


@app.get("/scrape/cuaca/status")
def status_scrape_cuaca(x_api_key: str = Header(None)):
    cek_key(x_api_key)
    return state_cuaca

@app.get("/health")
def health():
    return {
        "status": "ok",
        "scraping_harga": state_harga["berjalan"],
        "scraping_cuaca": state_cuaca["berjalan"],
    }