import os
import subprocess
import sys
from typing import Optional
import json
from datetime import date
from pydantic import BaseModel
from typing import Literal

from dotenv import load_dotenv
from fastapi import BackgroundTasks, FastAPI, Header, HTTPException

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
load_dotenv(os.path.join(BASE_DIR, ".env"))

# Ambil key
API_KEY = os.getenv("ML_SERVICE_KEY")

PRICE_DIR = os.path.join(BASE_DIR, "scraping", "price")
WEATHER_DIR = os.path.join(BASE_DIR, "scraping", "weather")

TRAINING_DIR = os.path.join(BASE_DIR, "training")

state_training = {
    "berjalan": False,
    "status": "idle",
    "request": None,
    "result": None,
    "error": None,
}
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

class TrainRequest(BaseModel):
    komoditas: int
    wilayah: int
    dari: date
    sampai: date
    model: Literal["LSTM", "BiLSTM", "GRU"]
    arsitektur: Literal["A", "B"]
    strategi: Literal["ST-1", "ST-2", "ST-3"]
    target_mode: Literal["price", "difference"] = "price"


def ambil_json_hasil(output: str):
    """
    Mencari objek JSON hasil eksperimen pada stdout train.py.
    Output training dapat berisi log TensorFlow sebelum JSON.
    """
    decoder = json.JSONDecoder()

    for posisi, karakter in enumerate(output):
        if karakter != "{":
            continue

        try:
            obj, _ = decoder.raw_decode(output[posisi:])
        except json.JSONDecodeError:
            continue

        if (
            isinstance(obj, dict)
            and "kode_skenario" in obj
            and "metrik" in obj
        ):
            return obj

    return None

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

def jalankan_training(params: dict):
    try:
        command = [
            sys.executable,
            "train.py",
            "--komoditas", str(params["komoditas"]),
            "--wilayah", str(params["wilayah"]),
            "--dari", params["dari"],
            "--sampai", params["sampai"],
            "--model", params["model"],
            "--arsitektur", params["arsitektur"],
            "--strategi", params["strategi"],
            "--target-mode", params["target_mode"],
        ]

        proses = subprocess.run(
            command,
            cwd=TRAINING_DIR,
            capture_output=True,
            text=True,
            timeout=3600,
        )

        if proses.returncode != 0:
            detail = proses.stderr or proses.stdout

            state_training.update({
                "status": "failed",
                "berjalan": False,
                "error": detail[-3000:],
                "result": None,
            })
            return

        hasil = ambil_json_hasil(proses.stdout)

        if hasil is None:
            state_training.update({
                "status": "failed",
                "berjalan": False,
                "error": (
                    "Training selesai, tetapi JSON hasil eksperimen "
                    "tidak ditemukan pada output train.py."
                ),
                "result": None,
            })
            return

        state_training.update({
            "status": "completed",
            "berjalan": False,
            "error": None,
            "result": hasil,
        })

    except subprocess.TimeoutExpired:
        state_training.update({
            "status": "failed",
            "berjalan": False,
            "error": "Training melewati batas waktu 3600 detik.",
            "result": None,
        })

    except Exception as exc:
        state_training.update({
            "status": "failed",
            "berjalan": False,
            "error": str(exc),
            "result": None,
        })

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
        "training": state_training["berjalan"],
    }

@app.post("/train", status_code=202)
def mulai_training(
    request: TrainRequest,
    background: BackgroundTasks,
    x_api_key: str = Header(None),
):
    cek_key(x_api_key)

    if state_training["berjalan"]:
        raise HTTPException(
            status_code=409,
            detail="Training masih berjalan.",
        )

    if request.sampai < request.dari:
        raise HTTPException(
            status_code=422,
            detail="Tanggal akhir harus sama atau setelah tanggal awal.",
        )

    if not os.path.isfile(os.path.join(TRAINING_DIR, "train.py")):
        raise HTTPException(
            status_code=500,
            detail="File training/train.py tidak ditemukan.",
        )

    params = {
        "komoditas": request.komoditas,
        "wilayah": request.wilayah,
        "dari": request.dari.isoformat(),
        "sampai": request.sampai.isoformat(),
        "model": request.model,
        "arsitektur": request.arsitektur,
        "strategi": request.strategi,
        "target_mode": request.target_mode,
    }

    state_training.update({
        "berjalan": True,
        "status": "running",
        "request": params,
        "result": None,
        "error": None,
    })

    background.add_task(jalankan_training, params)

    return {
        "message": "Training dimulai.",
        "status": "running",
        "request": params,
    }


@app.get("/train/status")
def status_training(x_api_key: str = Header(None)):
    cek_key(x_api_key)

    return state_training