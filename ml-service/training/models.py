import tensorflow as tf
from keras import layers, models


def build_model(
    kind,
    units,
    window,
    n_features=8,
    lr=0.01,
    dropout_rate=0.3,
    horizon=1,
):
    """
    horizon: jumlah hari yang diprediksi sekaligus.
    Output model berbentuk (batch, horizon).
    """
    cell = layers.GRU if kind == "GRU" else layers.LSTM

    model = models.Sequential([
        layers.Input(shape=(window, n_features))
    ])

    for i, u in enumerate(units):
        layer = cell(
            u,
            return_sequences=(i < len(units) - 1),
        )

        if kind == "BiLSTM":
            model.add(layers.Bidirectional(layer))
        else:
            model.add(layer)

        if dropout_rate > 0:
            model.add(layers.Dropout(dropout_rate))

    # Satu neuron per hari yang diprediksi.
    model.add(layers.Dense(horizon))

    model.compile(
        optimizer=tf.keras.optimizers.Adam(learning_rate=lr),
        loss="mse",
    )

    return model