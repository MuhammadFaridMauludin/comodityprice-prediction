import mysql.connector
import numpy as np
import pandas as pd
from sklearn.preprocessing import MinMaxScaler

from config import DB, SPLIT
from calendar_features import calendar_features


# ============================================================
# 1. KONFIGURASI FITUR
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

FEATURE_COLS = ["harga"] + WEATHER_COLS + EVENT_COLS


# ============================================================
# 2. LOAD DATA HARGA DAN CUACA
# ============================================================

def load_series(id_komoditas, id_wilayah, dari, sampai):
    """
    Mengambil data harga untuk satu komoditas dan wilayah,
    lalu menggabungkannya dengan data cuaca agregat harian
    serta fitur kalender.

    Returns
    -------
    df : pandas.DataFrame
        Data harian dengan index tanggal.
    feature_cols : list
        Delapan fitur input model.
    """

    conn = mysql.connector.connect(**DB)

    try:
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
            params=(id_komoditas, id_wilayah, dari, sampai),
        )

        cuaca = pd.read_sql(
        """
        SELECT
            tanggal,
            suhu,
            kelembapan,
            curah_hujan,
            jumlah_titik
        FROM data_cuacaharian
        WHERE id_komoditas = %s
            AND tanggal BETWEEN %s AND %s
        ORDER BY tanggal
        """,
            conn,
            params=(id_komoditas, dari, sampai),
        )

    finally:
        conn.close()

    if harga.empty:
        raise ValueError(
            "Data harga kosong. Periksa ID komoditas, "
            "wilayah, dan rentang tanggal."
        )

    if cuaca.empty:
        raise ValueError("Data cuaca agregat harian kosong.")

    # Normalisasi tanggal.
    harga["tanggal"] = pd.to_datetime(harga["tanggal"]).dt.normalize()
    cuaca["tanggal"] = pd.to_datetime(cuaca["tanggal"]).dt.normalize()

    if harga["tanggal"].duplicated().any():
        raise ValueError("Tanggal harga duplikat ditemukan.")

    if cuaca["tanggal"].duplicated().any():
        raise ValueError("Tanggal cuaca duplikat ditemukan.")

    # Buat kalender harian lengkap.
    dates = pd.date_range(
        start=pd.Timestamp(dari).normalize(),
        end=pd.Timestamp(sampai).normalize(),
        freq="D",
    )

    df = pd.DataFrame({"tanggal": dates})

    # Gabungkan harga dan cuaca.
    df = df.merge(harga, on="tanggal", how="left", validate="one_to_one")

    df = df.merge(
        cuaca[["tanggal", "suhu", "kelembapan", "curah_hujan", "jumlah_titik"]],
        on="tanggal",
        how="left",
        validate="one_to_one",
    )

    # Forward fill hanya untuk cuaca, menggunakan pengamatan sebelumnya.
    df[WEATHER_COLS] = df[WEATHER_COLS].ffill()

    # Jangan mengisi harga yang hilang dengan data masa depan.
    df = df.dropna(subset=["harga"] + WEATHER_COLS).copy()

    numeric_cols = ["harga"] + WEATHER_COLS

    df[numeric_cols] = df[numeric_cols].apply(pd.to_numeric, errors="coerce")
    df = df.replace([np.inf, -np.inf], np.nan)
    df = df.dropna(subset=numeric_cols).copy()

    if df.empty:
        raise ValueError("Tidak ada data valid setelah preprocessing.")

    if (df["harga"] <= 0).any():
        raise ValueError("Harga harus lebih besar dari nol.")

    # Buat fitur kalender.
    events = calendar_features(df["tanggal"])

    missing_events = [c for c in EVENT_COLS if c not in events.columns]

    if missing_events:
        raise ValueError(f"Fitur kalender tidak ditemukan: {missing_events}")

    if len(events) != len(df):
        raise ValueError(
            "Jumlah baris fitur kalender tidak sesuai "
            "dengan jumlah baris dataset."
        )

    events = events.reset_index(drop=True)
    df = df.reset_index(drop=True)

    df[EVENT_COLS] = events[EVENT_COLS].to_numpy(dtype=np.int8)

    df = df.sort_values("tanggal").reset_index(drop=True)

    if df[FEATURE_COLS].isna().any().any():
        raise ValueError("Masih ada nilai kosong pada fitur model.")

    df = df.set_index("tanggal").sort_index()

    print("\n=== DATASET MULTIVARIATE ===")
    print(f"Jumlah baris  : {len(df)}")
    print(f"Jumlah fitur  : {len(FEATURE_COLS)}")
    print(f"Fitur         : {FEATURE_COLS}")
    print(f"Tanggal awal  : {df.index.min().date()}")
    print(f"Tanggal akhir : {df.index.max().date()}")
    print(f"Harga minimum : {df['harga'].min():,.2f}")
    print(f"Harga maksimum: {df['harga'].max():,.2f}")

    return df, FEATURE_COLS


# ============================================================
# 3. MEMBUAT WINDOW TIME SERIES (MULTI-STEP)
# ============================================================

def make_windows(X, y, window, horizon=1):
    """
    Membuat sampel sequence untuk prediksi `horizon` hari ke depan.

    X: (jumlah_hari, jumlah_fitur)
    y: (jumlah_hari,)

    Untuk indeks i:
        input  = X[i - window : i]        (window hari terakhir)
        target = y[i : i + horizon]       (horizon hari berikutnya)

    Output:
    X_out: (jumlah_sampel, window, jumlah_fitur)
    y_out: (jumlah_sampel, horizon)
    indices: indeks hari target PERTAMA (i).
    """

    X_out = []
    y_out = []
    indices = []

    for i in range(window, len(X) - horizon + 1):
        X_out.append(X[i - window:i])
        y_out.append(y[i:i + horizon])
        indices.append(i)

    return (
        np.asarray(X_out, dtype=np.float32),
        np.asarray(y_out, dtype=np.float32),
        np.asarray(indices, dtype=np.int64),
    )


def stack_prices(prices, idx, horizon):
    """
    Harga aktual (N, horizon) dan baseline naive (N, horizon).
    Baseline naive: harga terakhir yang diketahui diulang
    sepanjang horizon.
    """
    actual = np.stack([prices[i:i + horizon] for i in idx])
    last_known = prices[idx - 1]
    naive = np.repeat(last_known[:, None], horizon, axis=1)
    return actual, naive, last_known


# ============================================================
# 4. PREPROCESSING DAN SPLIT KRONOLOGIS
# ============================================================

def prepare(
    df,
    window,
    feature_cols,
    target_mode="price",
    horizon=1,
):
    """
    Menyiapkan data train, validation, dan test
    berdasarkan urutan waktu.

    target_mode:
        price      : prediksi harga `horizon` hari ke depan.
        difference : prediksi perubahan harga harian
                     sepanjang `horizon` hari.

    horizon:
        jumlah hari yang diprediksi sekaligus (1, 7, 30, ...).

    Evaluasi dilakukan dalam skala harga asli.
    """

    if target_mode not in ("price", "difference"):
        raise ValueError("target_mode harus 'price' atau 'difference'.")

    if not isinstance(window, int) or window < 1:
        raise ValueError("window harus berupa integer positif.")

    if not isinstance(horizon, int) or horizon < 1:
        raise ValueError("horizon harus berupa integer positif.")

    df = df.sort_index().copy()

    expected_dates = pd.date_range(
        start=df.index.min(),
        end=df.index.max(),
        freq="D",
    )

    if not df.index.equals(expected_dates):
        raise ValueError(
            "Tanggal dataset tidak berurutan harian. "
            "Periksa data harga yang hilang sebelum training."
        )

    if df.index.has_duplicates:
        raise ValueError("Index tanggal duplikat ditemukan.")

    missing_cols = [c for c in feature_cols if c not in df.columns]

    if missing_cols:
        raise ValueError(f"Kolom fitur tidak ditemukan: {missing_cols}")

    if df[feature_cols].isna().any().any():
        raise ValueError("Data masih memiliki nilai kosong.")

    if (df["harga"] <= 0).any():
        raise ValueError("Harga harus lebih besar dari nol.")

    X_values = df[feature_cols].to_numpy(dtype=np.float64)
    prices = df["harga"].to_numpy(dtype=np.float64)

    n = len(df)

    # Batas split berdasarkan jumlah baris kronologis.
    i_tr = int(n * SPLIT[0])
    i_va = int(n * (SPLIT[0] + SPLIT[1]))

    if i_tr <= window or i_va <= i_tr or n <= i_va:
        raise ValueError(
            "Data tidak cukup untuk window dan split "
            "train/validation/test."
        )

    # Tentukan target.
    if target_mode == "price":
        y_values = prices.reshape(-1, 1)
        y_fit = y_values[:i_tr]

    else:
        # delta[t] = harga[t] - harga[t-1]
        delta = np.zeros(n, dtype=np.float64)
        delta[1:] = prices[1:] - prices[:-1]

        y_values = delta.reshape(-1, 1)
        y_fit = y_values[1:i_tr]

    # Fit scaler hanya menggunakan data train.
    x_scaler = MinMaxScaler()
    x_scaler.fit(X_values[:i_tr])

    y_scaler = MinMaxScaler()
    y_scaler.fit(y_fit)

    X_scaled = x_scaler.transform(X_values)
    y_scaled = y_scaler.transform(y_values).ravel()

    X_all, y_all, idx = make_windows(
        X_scaled,
        y_scaled,
        window,
        horizon,
    )

    # Pembagian berdasarkan rentang target (i ... i + horizon - 1).
    # Seluruh target sebuah sampel harus berada di bagian yang sama,
    # agar target train tidak "menyeberang" ke validation/test.
    last_target = idx + horizon          # eksklusif
    train_mask = last_target <= i_tr
    val_mask = (idx >= i_tr) & (last_target <= i_va)
    test_mask = idx >= i_va

    X_tr, y_tr = X_all[train_mask], y_all[train_mask]
    X_va, y_va = X_all[val_mask], y_all[val_mask]
    X_te, y_te = X_all[test_mask], y_all[test_mask]

    if len(X_tr) == 0 or len(X_va) == 0 or len(X_te) == 0:
        raise ValueError(
            "Salah satu bagian train, validation, atau "
            "test tidak memiliki sampel. Kecilkan window/horizon "
            "atau tambah data."
        )

    val_idx = idx[val_mask]
    test_idx = idx[test_mask]

    # Harga aktual (N, horizon), naive (N, horizon),
    # dan harga terakhir yang diketahui (N,).
    actual_va, naive_va, last_va = stack_prices(prices, val_idx, horizon)
    actual_te, naive_te, last_te = stack_prices(prices, test_idx, horizon)

    print("\n=== PEMBAGIAN DATA ===")
    print(f"Target mode    : {target_mode}")
    print(f"Window size    : {window}")
    print(f"Horizon        : {horizon}")
    print(f"Total baris    : {n}")
    print(f"Train          : {len(X_tr)} sampel")
    print(f"Validation     : {len(X_va)} sampel")
    print(f"Test           : {len(X_te)} sampel")
    print(f"Bentuk X_train : {X_tr.shape}")
    print(f"Bentuk y_train : {y_tr.shape}")
    print(f"Bentuk X_test  : {X_te.shape}")

    return {
        "x_scaler": x_scaler,
        "y_scaler": y_scaler,

        "X_tr": X_tr,
        "y_tr": y_tr,

        "X_va": X_va,
        "y_va": y_va,

        "X_te": X_te,
        "y_te": y_te,

        "y_true_price_va": actual_va,
        "naive_price_va": naive_va,
        "last_price_va": last_va,

        "y_true_price_te": actual_te,
        "naive_price_te": naive_te,
        "last_price_te": last_te,

        "n_total": n,
        "n_train": len(X_tr),
        "n_val": len(X_va),
        "n_test": len(X_te),

        "n_features": len(feature_cols),
        "feature_cols": feature_cols,

        "split_train_end": i_tr,
        "split_val_end": i_va,
        "target_mode": target_mode,
        "horizon": horizon,
        "test_idx": test_idx,
        "window": window,
    }