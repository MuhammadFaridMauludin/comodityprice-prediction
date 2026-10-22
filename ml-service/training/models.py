import tensorflow as tf
from keras import layers, models


def build_model(
    kind,
    units,
    window,
    n_features=8,
    lr=0.005,
    dropout_rate=0.3,
    horizon=1,
    dense_units=0,
):
    """
    kind        : "LSTM", "BiLSTM", atau "GRU".
    units       : daftar unit tiap lapisan rekuren, mis. [100, 100].
    dense_units : 0 = tanpa Dense tengah; >0 = Dense(dense_units, relu)
                  sebelum lapisan output (untuk uji tuning).
    horizon     : jumlah hari yang diprediksi sekaligus.
    Output model berbentuk (batch, horizon).
    """
    cell = layers.GRU if kind == "GRU" else layers.LSTM

    model = models.Sequential([
        layers.Input(shape=(window, n_features))
    ])

    for i, u in enumerate(units):
        layer = cell(
            u,
            activation="tanh",
            return_sequences=(i < len(units) - 1),
        )

        if kind == "BiLSTM":
            model.add(layers.Bidirectional(layer))
        else:
            model.add(layer)

        if dropout_rate > 0:
            model.add(layers.Dropout(dropout_rate))

    if dense_units > 0:
        model.add(layers.Dense(dense_units, activation="relu"))

    # Satu neuron per hari yang diprediksi.
    model.add(layers.Dense(horizon))

    model.compile(
        optimizer=tf.keras.optimizers.Adam(learning_rate=lr),
        loss="mse",
    )

    return model