import numpy as np
import pandas as pd
import keras
from sklearn.preprocessing import MinMaxScaler
from sklearn.metrics import mean_squared_error, mean_absolute_error, mean_absolute_percentage_error
from keras.models import Sequential
from keras.layers import Input, LSTM, Bidirectional, GRU, Dense, Dropout
from keras.optimizers import Adam
from keras.callbacks import EarlyStopping
from hijridate import Gregorian

# KONFIGURASI UMUM
TARGET_WILAYAH = 'Kota Surabaya'
WINDOW_SIZE = 7
BATCH_SIZE = 16
LEARNING_RATE = 0.001
DROPOUT = 0.2
SEED = 42

# Fitur yang dipakai di semua eksperimen (skenario 4 + Idul Adha + Nataru)
FEATURE_COLS = [
    'harga',
    'suhu', 'kelembapan', 'curah_hujan', 'suhu_lag23',
    'is_ramadan', 'is_pre_eid',
    'is_idul_adha', 'is_nataru',
]

# Definisi jendela hari raya (bisa disesuaikan)
# Idul Adha : 10 Dzulhijjah, jendela H-7 s.d. H -> tanggal Hijriah 3-10
ADHA_HARI_AWAL = 3
ADHA_HARI_AKHIR = 10
# Nataru    : H-7 Natal s.d. Tahun Baru -> 18 Desember s.d. 1 Januari
NATARU_MULAI_DES = 18
NATARU_AKHIR_JAN = 1

# KONFIGURASI EKSPERIMEN
# units    : daftar unit per lapisan, misal [128] atau [64, 32]
# epochs   : epoch maksimum
# patience : None = tanpa early stopping
EKSPERIMEN = [
    {"id": "S01", "model": "LSTM", "units": [128],    "strategi": "ST-1", "epochs": 50, "patience": None},
    {"id": "S10", "model": "LSTM", "units": [64, 32], "strategi": "ST-1", "epochs": 50, "patience": None},
]

# 1. LOAD & MERGE DATASET
df_harga = pd.read_csv('../data/price/harga-cabai.csv')
df_cuaca = pd.read_csv('../data/weather/cuaca_kediri.csv')

# Filter kolom tanggal & wilayah target
if TARGET_WILAYAH in df_harga.columns:
    df_harga = df_harga[['tanggal', TARGET_WILAYAH]].rename(columns={TARGET_WILAYAH: 'harga'})
else:
    raise KeyError(f"Kolom '{TARGET_WILAYAH}' tidak ditemukan di CSV harga!")

# Format kolom tanggal
df_harga['tanggal'] = pd.to_datetime(df_harga['tanggal'])
df_cuaca['tanggal'] = pd.to_datetime(df_cuaca['tanggal'])

# Penggabungan data berdasarkan tanggal
df = pd.merge(df_harga, df_cuaca, on='tanggal', how='outer').sort_values('tanggal').reset_index(drop=True)
df = df.ffill()

# 2. FEATURE ENGINEERING
# Lag Suhu 23 Hari
df['suhu_lag23'] = df['suhu'].shift(23)

# Fitur hari raya & libur panjang
def generate_event_features(date_val):
    hijri_date = Gregorian(date_val.year, date_val.month, date_val.day).to_hijri()

    # Ramadan & Pre-Eid (H-7 Idul Fitri)
    is_ramadan = 1 if hijri_date.month == 9 else 0
    is_pre_eid = 1 if (hijri_date.month == 9 and hijri_date.day >= 23) else 0

    # Idul Adha: 10 Dzulhijjah (bulan Hijriah ke-12), jendela H-7 s.d. H
    is_idul_adha = 1 if (
        hijri_date.month == 12
        and ADHA_HARI_AWAL <= hijri_date.day <= ADHA_HARI_AKHIR
    ) else 0

    # Nataru: H-7 Natal s.d. Tahun Baru (kalender Masehi)
    is_nataru = 1 if (
        (date_val.month == 12 and date_val.day >= NATARU_MULAI_DES)
        or (date_val.month == 1 and date_val.day <= NATARU_AKHIR_JAN)
    ) else 0

    return is_ramadan, is_pre_eid, is_idul_adha, is_nataru

event_data = df['tanggal'].apply(generate_event_features)
df['is_ramadan'] = [x[0] for x in event_data]
df['is_pre_eid'] = [x[1] for x in event_data]
df['is_idul_adha'] = [x[2] for x in event_data]
df['is_nataru'] = [x[3] for x in event_data]

# Menghapus baris NaN akibat shift(23)
df = df.dropna().reset_index(drop=True)

# Ringkasan jumlah hari tiap fitur event (untuk memastikan fitur terisi)
print("Jumlah hari per fitur event:")
print(df[['is_ramadan', 'is_pre_eid', 'is_idul_adha', 'is_nataru']].sum().to_string())

# 3. FUNGSI PERSIAPAN DATA TIME-SERIES
def create_sequences(data, target_col_idx, window_size=7):
    X, y = [], []
    for i in range(len(data) - window_size):
        X.append(data[i : i + window_size, :])
        y.append(data[i + window_size, target_col_idx])
    return np.array(X), np.array(y)

# 4. MEMBANGUN MODEL SESUAI KONFIGURASI
def lapisan_rekuren(jenis, unit, return_sequences):
    """Mengembalikan satu lapisan LSTM / BiLSTM / GRU."""
    if jenis == "LSTM":
        return LSTM(unit, return_sequences=return_sequences)
    if jenis == "BiLSTM":
        return Bidirectional(LSTM(unit, return_sequences=return_sequences))
    if jenis == "GRU":
        return GRU(unit, return_sequences=return_sequences)
    raise ValueError(f"Jenis model tidak dikenal: {jenis}")


def build_model(jenis, units, input_shape):
    model = Sequential()
    model.add(Input(shape=input_shape))

    for i, unit in enumerate(units):
        terakhir = (i == len(units) - 1)
        # Lapisan yang masih diikuti lapisan rekuren lain wajib return_sequences=True
        model.add(lapisan_rekuren(jenis, unit, return_sequences=not terakhir))
        model.add(Dropout(DROPOUT))

    model.add(Dense(1))
    model.compile(optimizer=Adam(learning_rate=LEARNING_RATE), loss='mean_squared_error')
    return model

# 5. FUNGSI TRAIN & EVALUATE
def train_and_evaluate(cfg):
    print(f"\n==========================================")
    print(f" {cfg['id']} | {cfg['model']} {cfg['units']} | {cfg['strategi']}")
    print(f" Fitur ({len(FEATURE_COLS)}): {FEATURE_COLS}")
    print(f"==========================================")

    # Seed sama di setiap percobaan supaya perbandingan antar konfigurasi adil
    keras.utils.set_random_seed(SEED)
    keras.backend.clear_session()

    dataset = df[FEATURE_COLS].values

    # Split Data Kronologis Sebelum Scaling (80% Train, 20% Test)
    train_size = int(len(dataset) * 0.8)
    train_raw = dataset[:train_size]
    test_raw = dataset[train_size:]

    # Normalisasi (Fit hanya pada Data Latih)
    scaler = MinMaxScaler(feature_range=(0, 1))
    train_scaled = scaler.fit_transform(train_raw)
    test_scaled = scaler.transform(test_raw)

    # Buat Sequence
    target_idx = FEATURE_COLS.index('harga')
    X_train, y_train = create_sequences(train_scaled, target_idx, WINDOW_SIZE)
    X_test, y_test = create_sequences(test_scaled, target_idx, WINDOW_SIZE)

    # Model
    model = build_model(cfg['model'], cfg['units'], (X_train.shape[1], X_train.shape[2]))

    callbacks = []
    if cfg['patience'] is not None:
        callbacks.append(EarlyStopping(monitor='loss', patience=cfg['patience'], restore_best_weights=True))

    t_mulai = pd.Timestamp.now()
    history = model.fit(
        X_train, y_train,
        epochs=cfg['epochs'],
        batch_size=BATCH_SIZE,
        verbose=0,
        callbacks=callbacks,
    )
    durasi = (pd.Timestamp.now() - t_mulai).total_seconds()

    # Prediksi
    predictions = model.predict(X_test, verbose=0)

    # Denormalisasi Hasil Prediksi & Target Asli
    dummy_pred = np.zeros((len(predictions), len(FEATURE_COLS)))
    dummy_pred[:, target_idx] = predictions.flatten()
    actual_pred = scaler.inverse_transform(dummy_pred)[:, target_idx]

    dummy_actual = np.zeros((len(y_test), len(FEATURE_COLS)))
    dummy_actual[:, target_idx] = y_test
    actual_y = scaler.inverse_transform(dummy_actual)[:, target_idx]

    # Hitung Metrik Evaluasi
    rmse = np.sqrt(mean_squared_error(actual_y, actual_pred))
    mae = mean_absolute_error(actual_y, actual_pred)
    mape = mean_absolute_percentage_error(actual_y, actual_pred) * 100

    print(f" Epoch dijalankan: {len(history.history['loss'])} | Waktu: {durasi:.1f} dtk")
    print(f" Hasil -> RMSE: Rp {rmse:,.2f} | MAE: Rp {mae:,.2f} | MAPE: {mape:.2f}%")

    return {
        'ID': cfg['id'],
        'Model': cfg['model'],
        'Unit': " -> ".join(map(str, cfg['units'])),
        'Strategi': cfg['strategi'],
        'Jumlah Fitur': len(FEATURE_COLS),
        'Epoch': cfg['epochs'],
        'Batch Size': BATCH_SIZE,
        'Window Size': WINDOW_SIZE,
        'Learning Rate': LEARNING_RATE,
        'RMSE': rmse,
        'MAE': mae,
        'MAPE (%)': mape,
        'Durasi (dtk)': round(durasi, 1),
    }

# 6. EKSEKUSI: setiap konfigurasi dilatih satu kali dengan fitur lengkap
results = [train_and_evaluate(cfg) for cfg in EKSPERIMEN]

# 7. REKAPITULASI HASIL EKSPERIMEN
df_results = pd.DataFrame(results)

print("\n" + "=" * 60)
print(" REKAPITULASI PERBANDINGAN PERFORMA MODEL")
print("=" * 60)
print(df_results.to_string(index=False))

df_results.to_csv('hasil_eksperimen.csv', index=False)
print("\nHasil disimpan ke hasil_eksperimen.csv")