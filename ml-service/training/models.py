import tensorflow as tf
from keras import layers, models


def build_model(
    kind,
    units,
    window,
    n_features=8,
    lr=0.001,
    dropout_rate=0.0,
    activation="tanh",
):
    if kind not in ("LSTM", "BiLSTM", "GRU"):
        raise ValueError(f"Model tidak dikenal: {kind}")

    if activation not in ("tanh", "relu"):
        raise ValueError("Aktivasi harus tanh atau relu.")

    cell = layers.GRU if kind == "GRU" else layers.LSTM

    model = models.Sequential([
        layers.Input(shape=(window, n_features))
    ])

    for i, u in enumerate(units):
        recurrent_layer = cell(
            units=u,
            activation=activation,
            return_sequences=(i < len(units) - 1),
        )

        if kind == "BiLSTM":
            model.add(layers.Bidirectional(recurrent_layer))
        else:
            model.add(recurrent_layer)

        if dropout_rate > 0:
            model.add(layers.Dropout(dropout_rate))

    model.add(layers.Dense(1, activation="linear"))

    model.compile(
        optimizer=tf.keras.optimizers.Adam(
            learning_rate=lr
        ),
        loss="mse",
    )

    return model