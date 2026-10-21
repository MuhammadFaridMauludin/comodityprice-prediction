
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

# Konfigurasi tetap untuk 18 skenario eksperimen awal.
FIXED = {
    "window_size": 7,
    "batch_size": 32,
    "learning_rate": 0.001,
}

MODELS = ["LSTM", "BiLSTM", "GRU"]

ARCHITECTURES = {
    "A": [128],
    "B": [64, 32],
}

STRATEGIES = {
    "ST-1": {"epochs": 50, "patience": None},
    "ST-2": {"epochs": 100, "patience": None},
    "ST-3": {"epochs": 100, "patience": 5},
}

SPLIT = (0.70, 0.15, 0.15)
SAVE_DIR = ROOT / "saved_models"

# Kandidat Grid Search.
# Ruang pencarian awal dibuat terbatas agar lebih realistis.
GRID_SEARCH = {
    # Window size
    "window_size": [7, 14, 28],

    # Arsitektur
    "architectures": {
        "A": [128],       # 1 lapisan, 128 unit
        "B": [64, 32],    # 2 lapisan, 64 dan 32 unit
    },

    # Hyperparameter
    "learning_rate": [0.01, 0.001],
    "batch_size": [32],
    "activation": ["tanh", "relu"],
    "dropout_rate": [0.0, 0.1, 0.2, 0.3],

    # Strategi pelatihan
    "training_strategies": [
        {
            "name": "E100",
            "epochs": 100,
            "patience": 5,
        }
    ],
}

def scenario_code(model, arch, strategy):
    """Mempertahankan penomoran S01-S18 eksperimen awal."""
    a = list(ARCHITECTURES).index(arch)
    s = list(STRATEGIES).index(strategy)
    m = MODELS.index(model)
    return f"S{a * 9 + s * 3 + m + 1:02d}"
