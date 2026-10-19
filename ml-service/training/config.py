import os
from pathlib import Path
from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parents[1]   # folder ml-service
load_dotenv(ROOT / ".env")

DB = dict(
    host=os.getenv("DB_HOST", "127.0.0.1"),
    port=int(os.getenv("DB_PORT", "3306")),
    user=os.getenv("DB_USER"),
    password=os.getenv("DB_PASSWORD", ""),
    database=os.getenv("DB_NAME"),
)

if not DB["user"] or not DB["database"]:
    raise RuntimeError(f"DB_USER / DB_NAME tidak ditemukan. Cek file .env di {ROOT}")

# Tetap untuk semua skenario
FIXED = {"window_size": 7, "batch_size": 32, "learning_rate": 0.001}

# Urutan ini menentukan nomor S01-S18
MODELS = ["LSTM", "BiLSTM", "GRU"]
ARCHITECTURES = {"A": [128], "B": [64, 32]}
STRATEGIES = {
    "ST-1": {"epochs": 50,  "patience": None},
    "ST-2": {"epochs": 100, "patience": None},
    "ST-3": {"epochs": 100, "patience": 5},
}

SPLIT = (0.70, 0.15, 0.15)   # latih, validasi, uji (kronologis)
SAVE_DIR = ROOT / "saved_models"


def scenario_code(model, arch, strategy):
    """LSTM/A/ST-1 -> S01 ... GRU/B/ST-3 -> S18"""
    a = list(ARCHITECTURES).index(arch)
    s = list(STRATEGIES).index(strategy)
    m = MODELS.index(model)
    return f"S{a * 9 + s * 3 + m + 1:02d}"