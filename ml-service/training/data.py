
import mysql.connector
import numpy as np
import pandas as pd
from sklearn.preprocessing import MinMaxScaler

from config import DB, SPLIT
from calendar_features import calendar_features


# ============================================================
# KONFIGURASI FITUR
# ============================================================

WEATHER_COLS = [
    "suhu",
    "kelembapan",
    "curah_hujan",
]

EVENT_COLS = [
    "is_ramadan",
    "pre_eid",
    "is_adha",
    "is_nataru",
]

# Total 8 fitur:
# 1 harga + 3 cuaca + 4 kalender
FEATURE_COLS = ["harga"] + WEATHER_COLS + EVENT_COLS


# ============================================================
# 1. LOAD DATA HARGA DAN CUACA
# ============================================================

def load_series(id_komoditas, id_wilayah, dari, sampai):
    """
    Mengambil data harga komoditas untuk satu wilayah
    dan data cuaca agregat harian dari database.

    Returns
    -------
    df : DataFrame
        Data harian dengan fitur harga, cuaca, dan kalender.
    feature_cols : list
        Daftar fitur input model.
    """

    conn = mysql.connector.connect(**DB)

    try:
        # ----------------------------------------------------
        # Ambil harga komoditas untuk wilayah target
        # ----------------------------------------------------
        harga = pd.read_sql(
            """
            SELECT
                tanggal,
                AVG(harga) AS harga
            FROM data_harga
            WHERE id_komoditas = %s
              AND id_wilayah = %s
              AND tanggal BETWEEN %s AND %s
              AND harga > 0
            GROUP BY tanggal
            ORDER BY tanggal
            """,
            conn,
            params=(
                id_komoditas,
                id_wilayah,
                dari,
                sampai,
            ),
        )

        # ----------------------------------------------------
        # Ambil data cuaca agregat harian
        # ----------------------------------------------------
        cuaca = pd.read_sql(
            """
            SELECT
                tanggal,
                suhu,
                kelembapan,
                curah_hujan,
                jumlah_titik
            FROM data_cuacaharian
            WHERE tanggal BETWEEN %s AND %s
            ORDER BY tanggal
            """,
            conn,
            params=(dari, sampai),
        )

    finally:
        conn.close()

    # --------------------------------------------------------
    # Validasi data
    # --------------------------------------------------------
    if harga.empty:
        raise ValueError(
            "Data harga kosong. Periksa id_komoditas, "
            "id_wilayah, dan rentang tanggal."
        )

    if cuaca.empty:
        raise ValueError(
            "Data cuacaharian kosong untuk periode tersebut."
        )

    # --------------------------------------------------------
    # Normalisasi format tanggal
    # --------------------------------------------------------
    harga["tanggal"] = pd.to_datetime(harga["tanggal"])
    cuaca["tanggal"] = pd.to_datetime(cuaca["tanggal"])

    harga["tanggal"] = harga["tanggal"].dt.normalize()
    cuaca["tanggal"] = cuaca["tanggal"].dt.normalize()

    if harga["tanggal"].duplicated().any():
        raise ValueError(
            "Terdapat tanggal harga duplikat setelah agregasi."
        )

    if cuaca["tanggal"].duplicated().any():
        raise ValueError(
            "Terdapat tanggal duplikat pada data cuacaharian."
        )

    # --------------------------------------------------------
    # Buat kalender harian lengkap
    # --------------------------------------------------------
    dates = pd.date_range(
        start=pd.Timestamp(dari),
        end=pd.Timestamp(sampai),
        freq="D",
    )

    dates = dates.normalize()

    df = pd.DataFrame({
        "tanggal": dates,
    })

    # --------------------------------------------------------
    # Gabungkan harga berdasarkan tanggal
    # --------------------------------------------------------
    df = df.merge(
        harga,
        on="tanggal",
        how="left",
        validate="one_to_one",
    )

    # --------------------------------------------------------
    # Gabungkan cuaca berdasarkan tanggal
    # --------------------------------------------------------
    df = df.merge(
        cuaca[
            [
                "tanggal",
                "suhu",
                "kelembapan",
                "curah_hujan",
                "jumlah_titik",
            ]
        ],
        on="tanggal",
        how="left",
        validate="one_to_one",
    )

    # --------------------------------------------------------
    # Validasi dan isi data cuaca yang hilang
    # --------------------------------------------------------
    # Forward fill hanya menggunakan pengamatan sebelumnya.
    # Nilai sebelum pengamatan cuaca pertama tidak diisi
    # menggunakan data masa depan.
    df[WEATHER_COLS] = df[WEATHER_COLS].ffill()

    # Jangan otomatis mengisi harga yang hilang.
    # Harga yang tidak tersedia harus ditangani secara eksplisit.
    df = df.dropna(
        subset=["harga"] + WEATHER_COLS
    ).copy()

    if df.empty:
        raise ValueError(
            "Tidak ada baris valid setelah penggabungan harga "
            "dan cuaca."
        )

    # --------------------------------------------------------
    # Validasi nilai numerik
    # --------------------------------------------------------
    numeric_cols = ["harga"] + WEATHER_COLS

    df[numeric_cols] = df[numeric_cols].apply(
        pd.to_numeric,
        errors="coerce",
    )

    df = df.replace(
        [np.inf, -np.inf],
        np.nan,
    )

    df = df.dropna(
        subset=numeric_cols
    ).copy()

    if df.empty:
        raise ValueError(
            "Tidak ada data numerik valid untuk pelatihan."
        )

    if (df["harga"] <= 0).any():
        raise ValueError(
            "Harga harus lebih besar dari nol."
        )

    # --------------------------------------------------------
    # Buat fitur kalender
    # --------------------------------------------------------
    # Fungsi calendar_features() harus menghasilkan kolom:
    # is_ramadan, pre_eid, is_adha, is_nataru.
    events = calendar_features(df["tanggal"])

    # Validasi nama kolom sebelum assignment
    missing_event_cols = [
        col for col in EVENT_COLS
        if col not in events.columns
    ]

    if missing_event_cols:
        raise ValueError(
            "Kolom kalender tidak ditemukan: "
            f"{missing_event_cols}. "
            f"Kolom yang tersedia: {events.columns.tolist()}. "
            "Periksa calendar_features.py."
        )

    # Assignment berdasarkan urutan baris, bukan label index.
    # Ini menghindari KeyError atau ketidakcocokan index.
    if len(events) != len(df):
        raise ValueError(
            "Jumlah baris fitur kalender tidak sama "
            "dengan jumlah baris data."
        )

    events = events.reset_index(drop=True)
    df = df.reset_index(drop=True)

    df[EVENT_COLS] = events[EVENT_COLS].to_numpy(
        dtype=np.int8
    )

    # --------------------------------------------------------
    # Pemeriksaan akhir
    # --------------------------------------------------------
    df = df.sort_values("tanggal").reset_index(drop=True)

    if df[FEATURE_COLS].isna().any().any():
        raise ValueError(
            "Masih ditemukan nilai kosong pada fitur model."
        )

    # Tanggal menjadi index untuk analisis time series.
    df = df.set_index("tanggal").sort_index()

    print("\n=== DATASET MULTIVARIATE ===")
    print(f"Jumlah baris       : {len(df)}")
    print(f"Jumlah fitur       : {len(FEATURE_COLS)}")
    print(f"Fitur              : {FEATURE_COLS}")
    print(f"Tanggal awal       : {df.index.min().date()}")
    print(f"Tanggal akhir      : {df.index.max().date()}")
    print(f"Harga minimum      : {df['harga'].min():,.2f}")
    print(f"Harga maksimum     : {df['harga'].max():,.2f}")
    print(
        f"Cuaca tersedia     : "
        f"{df[WEATHER_COLS].notna().all(axis=1).sum()} baris"
    )

    return df, FEATURE_COLS


# ============================================================
# 2. MEMBUAT WINDOW TIME SERIES
# ============================================================

def make_windows(X, y, window):
    """
    Membuat window multivariate.

    Input:
        X: (jumlah_hari, jumlah_fitur)
        y: (jumlah_hari,)
        window: jumlah timestep historis

    Output:
        X: (jumlah_sampel, window, jumlah_fitur)
        y: (jumlah_sampel,)
        indices: indeks tanggal target.
    """

    X_out = []
    y_out = []
    indices = []

    for i in range(window, len(X)):
        X_out.append(
            X[i - window:i]
        )

        y_out.append(
            y[i]
        )

        indices.append(i)

    return (
        np.asarray(X_out, dtype=np.float32),
        np.asarray(y_out, dtype=np.float32),
        np.asarray(indices, dtype=np.int64),
    )


# ============================================================
# 3. PREPROCESSING DAN SPLIT KRONOLOGIS
# ============================================================

def prepare(df, window, feature_cols):
    """
    Menyiapkan data train, validation, dan test.

    Split dilakukan secara kronologis:
        70% train
        15% validation
        15% test

    Scaler hanya di-fit pada data train.
    Target prediksi adalah harga hari berikutnya.
    """

    # Pastikan tanggal terurut dan tidak duplikat.
    df = df.sort_index().copy()

    if df.index.has_duplicates:
        raise ValueError(
            "Index tanggal duplikat ditemukan."
        )

    required_cols = ["harga"] + [
        col for col in feature_cols
        if col != "harga"
    ]

    missing_cols = [
        col for col in required_cols
        if col not in df.columns
    ]

    if missing_cols:
        raise ValueError(
            f"Kolom fitur tidak ditemukan: {missing_cols}"
        )

    # Pastikan semua fitur numerik dan lengkap.
    if df[required_cols].isna().any().any():
        raise ValueError(
            "Data masih memiliki nilai kosong. "
            "Periksa preprocessing sebelum prepare()."
        )

    X_values = df[feature_cols].to_numpy(
        dtype=np.float64
    )

    y_values = df["harga"].to_numpy(
        dtype=np.float64
    ).reshape(-1, 1)

    n = len(df)

    # --------------------------------------------------------
    # Tentukan batas split berdasarkan jumlah baris
    # --------------------------------------------------------
    i_tr = int(n * SPLIT[0])
    i_va = int(n * (SPLIT[0] + SPLIT[1]))

    if (
        i_tr <= window
        or i_va <= i_tr
        or n <= i_va
    ):
        raise ValueError(
            "Jumlah data tidak cukup untuk window "
            "dan pembagian train/validation/test."
        )

    # --------------------------------------------------------
    # Fit scaler hanya menggunakan data train
    # --------------------------------------------------------
    x_scaler = MinMaxScaler()

    x_scaler.fit(
        X_values[:i_tr]
    )

    y_scaler = MinMaxScaler()

    y_scaler.fit(
        y_values[:i_tr]
    )

    # Transform seluruh data menggunakan scaler train.
    X_scaled = x_scaler.transform(
        X_values
    )

    y_scaled = y_scaler.transform(
        y_values
    ).ravel()

    # --------------------------------------------------------
    # Buat window
    # --------------------------------------------------------
    X_all, y_all, idx = make_windows(
        X_scaled,
        y_scaled,
        window,
    )

    # --------------------------------------------------------
    # Bagi berdasarkan tanggal target
    # --------------------------------------------------------
    train_mask = idx < i_tr

    val_mask = (
        (idx >= i_tr)
        & (idx < i_va)
    )

    test_mask = idx >= i_va

    X_tr = X_all[train_mask]
    y_tr = y_all[train_mask]

    X_va = X_all[val_mask]
    y_va = y_all[val_mask]

    X_te = X_all[test_mask]
    y_te = y_all[test_mask]

    if (
        len(X_tr) == 0
        or len(X_va) == 0
        or len(X_te) == 0
    ):
        raise ValueError(
            "Salah satu segmen train, validation, "
            "atau test tidak memiliki sampel."
        )

    # --------------------------------------------------------
    # Informasi split
    # --------------------------------------------------------
    print("\n=== PEMBAGIAN DATA ===")
    print(f"Total baris data   : {n}")
    print(f"Window size        : {window}")
    print(f"Jumlah fitur       : {len(feature_cols)}")
    print(
        f"Train              : {len(X_tr)} sampel "
        f"({len(X_tr) / len(X_all) * 100:.2f}%)"
    )
    print(
        f"Validation         : {len(X_va)} sampel "
        f"({len(X_va) / len(X_all) * 100:.2f}%)"
    )
    print(
        f"Test               : {len(X_te)} sampel "
        f"({len(X_te) / len(X_all) * 100:.2f}%)"
    )
    print(f"Bentuk X_train     : {X_tr.shape}")
    print(f"Bentuk X_val       : {X_va.shape}")
    print(f"Bentuk X_test      : {X_te.shape}")

    return {
        "x_scaler": x_scaler,
        "y_scaler": y_scaler,

        "X_tr": X_tr,
        "y_tr": y_tr,

        "X_va": X_va,
        "y_va": y_va,

        "X_te": X_te,
        "y_te": y_te,

        "n_total": n,
        "n_train": len(X_tr),
        "n_val": len(X_va),
        "n_test": len(X_te),

        "n_features": len(feature_cols),
        "feature_cols": feature_cols,

        "split_train_end": i_tr,
        "split_val_end": i_va,
    }
