
import tensorflow as tf
from keras import layers, models


def build_model(kind, units, window, n_features=8, lr=0.001):
    cell = layers.GRU if kind == "GRU" else layers.LSTM

    model = models.Sequential([
        layers.Input(shape=(window, n_features))
    ])

    for i, u in enumerate(units):
        layer = cell(
            u,
            return_sequences=(i < len(units) - 1)
        )

        if kind == "BiLSTM":
            model.add(layers.Bidirectional(layer))
        else:
            model.add(layer)

    model.add(layers.Dense(1))

    model.compile(
        optimizer=tf.keras.optimizers.Adam(
            learning_rate=lr
        ),
        loss="mse",
    )

    return model
