import os
from pathlib import Path
from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parents[1]
load_dotenv(ROOT / ".env")

DB = dict(
    host=os.getenv("DB_HOST", "127.0.0.1"),
    port=int(os.getenv("DB_PORT", "3306")),
    user=os.getenv("DB_USER"),
    password=os.getenv("DB_PASSWORD", ""),
    database=os.getenv("DB_NAME"),
)

if not DB["user"] or not DB["database"]:
    raise RuntimeError(
        f"DB_USER / DB_NAME tidak ditemukan. Cek file .env di {ROOT}"
    )

# Nilai awal; window dan learning rate dapat di-override lewat CLI
# saat tuning (--window, --lr).
FIXED = {
    "window_size": 7,
    "batch_size": 32,
    "learning_rate": 0.005,
}

MODELS = ["LSTM", "BiLSTM", "GRU"]

# Arsitektur "K": mengacu pada hyperparameter Khan dkk. (2024), Tabel 1.
ARCHITECTURES = {
    "K": {
        "LSTM":   {"units": [100, 100], "dropout": 0.3},
        "BiLSTM": {"units": [100, 100], "dropout": 0.3},
        "GRU":    {"units": [100],      "dropout": 0.2},
    },
}

STRATEGIES = {
    "ST-1": {"epochs": 50, "patience": None},
    "ST-2": {"epochs": 100, "patience": None},
    "ST-3": {"epochs": 100, "patience": 5},
}

SPLIT = (0.70, 0.15, 0.15)
SAVE_DIR = ROOT / "saved_models"


def scenario_code(model, arch, strategy):
    """K01-K09 (3 strategi x 3 model); tidak bentrok dengan S01-S18 lama."""
    s = list(STRATEGIES).index(strategy)
    m = MODELS.index(model)
    return f"{arch}{s * 3 + m + 1:02d}"