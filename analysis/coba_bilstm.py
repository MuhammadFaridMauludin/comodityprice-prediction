import numpy as np
import pandas as pd
from sklearn.preprocessing import MinMaxScaler
from sklearn.metrics import mean_squared_error, mean_absolute_error, mean_absolute_percentage_error
from keras.models import Sequential
from keras.layers import Bidirectional, LSTM, Dense, Dropout
from keras.optimizers import Adam
from hijridate import Gregorian

TARGET_WILAYAH = 'Kota Surabaya'

# 1. LOAD & MERGE DATASET
df_harga = pd.read_csv('../data/price/harga-cabai.csv')
df_cuaca = pd.read_csv('../data/weather/cuaca_kediri.csv')

df_harga = df_harga[['tanggal', TARGET_WILAYAH]].rename(columns={TARGET_WILAYAH: 'harga'})
df_harga['tanggal'] = pd.to_datetime(df_harga['tanggal'])
df_cuaca['tanggal'] = pd.to_datetime(df_cuaca['tanggal'])

df = pd.merge(df_harga, df_cuaca, on='tanggal', how='outer').sort_values('tanggal').reset_index(drop=True)
df = df.ffill()

# 2. FEATURE ENGINEERING
df['suhu_lag23'] = df['suhu'].shift(23)

def generate_ramadan_features(date_val):
    hijri_date = Gregorian(date_val.year, date_val.month, date_val.day).to_hijri()
    is_ramadan = 1 if hijri_date.month == 9 else 0
    is_pre_eid = 1 if (hijri_date.month == 9 and hijri_date.day >= 23) else 0
    return is_ramadan, is_pre_eid

ramadan_data = df['tanggal'].apply(generate_ramadan_features)
df['is_ramadan'] = [x[0] for x in ramadan_data]
df['is_pre_eid'] = [x[1] for x in ramadan_data]
df = df.dropna().reset_index(drop=True)

# 3. HELPER SEQUENCE
def create_sequences(data, target_col_idx, window_size=7):
    X, y = [], []
    for i in range(len(data) - window_size):
        X.append(data[i : i + window_size, :])
        y.append(data[i + window_size, target_col_idx])
    return np.array(X), np.array(y)

# 4. TRAIN & EVALUATE BiLSTM
def train_bilstm(feature_cols, scenario_name):
    print(f"\n==========================================")
    print(f" [BiLSTM] JALANKAN: {scenario_name}")
    print(f"==========================================")

    dataset = df[feature_cols].values
    train_size = int(len(dataset) * 0.8)
    train_raw, test_raw = dataset[:train_size], dataset[train_size:]

    scaler = MinMaxScaler(feature_range=(0, 1))
    train_scaled = scaler.fit_transform(train_raw)
    test_scaled = scaler.transform(test_raw)

    WINDOW_SIZE = 7
    target_idx = feature_cols.index('harga')

    X_train, y_train = create_sequences(train_scaled, target_idx, WINDOW_SIZE)
    X_test, y_test = create_sequences(test_scaled, target_idx, WINDOW_SIZE)

    # ARSITEKTUR BiLSTM (Bidirectional Wrapper)
    model = Sequential([
        Bidirectional(LSTM(64, return_sequences=True), input_shape=(X_train.shape[1], X_train.shape[2])),
        Dropout(0.2),
        Bidirectional(LSTM(32, return_sequences=False)),
        Dropout(0.2),
        Dense(1)
    ])

    model.compile(optimizer=Adam(learning_rate=0.001), loss='mean_squared_error')
    model.fit(X_train, y_train, epochs=50, batch_size=16, verbose=0)

    predictions = model.predict(X_test)

    # Denormalisasi
    dummy_pred = np.zeros((len(predictions), len(feature_cols)))
    dummy_pred[:, target_idx] = predictions.flatten()
    actual_pred = scaler.inverse_transform(dummy_pred)[:, target_idx]

    dummy_actual = np.zeros((len(y_test), len(feature_cols)))
    dummy_actual[:, target_idx] = y_test
    actual_y = scaler.inverse_transform(dummy_actual)[:, target_idx]

    # Metrik Evaluasi
    rmse = np.sqrt(mean_squared_error(actual_y, actual_pred))
    mae = mean_absolute_error(actual_y, actual_pred)
    mape = mean_absolute_percentage_error(actual_y, actual_pred) * 100

    print(f" BiLSTM Hasil -> RMSE: Rp {rmse:,.2f} | MAE: Rp {mae:,.2f} | MAPE: {mape:.2f}%")

    return {'Model': 'BiLSTM', 'Skenario': scenario_name, 'RMSE': rmse, 'MAE': mae, 'MAPE (%)': mape}

# 5. EKSEKUSI 4 SKENARIO BiLSTM
scenarios = [
    (['harga'], "Skenario 1: Hanya Harga"),
    (['harga', 'suhu', 'kelembapan', 'curah_hujan', 'suhu_lag23'], "Skenario 2: Harga + Cuaca"),
    (['harga', 'is_ramadan', 'is_pre_eid'], "Skenario 3: Harga + Ramadan"),
    (['harga', 'suhu', 'kelembapan', 'curah_hujan', 'suhu_lag23', 'is_ramadan', 'is_pre_eid'], "Skenario 4: Harga + Cuaca + Ramadan")
]

results_bilstm = []
for cols, name in scenarios:
    results_bilstm.append(train_bilstm(cols, name))

print("\nREKAPITULASI HASIL MODEL BiLSTM:")
print(pd.DataFrame(results_bilstm).to_string(index=False))