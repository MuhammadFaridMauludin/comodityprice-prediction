<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluasiModel extends Model
{
    protected $table = 'evaluasi_model';
    protected $primaryKey = 'id_evaluasi';
    public $timestamps = false;

    protected $fillable = [
        'id_model',
        'id_komoditas',
        'id_wilayah',
        'kode_skenario',
        'units',
        'epoch',
        'epoch_aktual',
        'early_stopping',
        'patience',
        'batch_size',
        'window_size',
        'horizon',
        'learning_rate',
        'dropout',
        'jumlah_parameter',
        'durasi_detik',
        'tanggal_evaluasi',
        'rmse',
        'rmse_validasi',
        'mae',
        'mape',
        'r2',
        'tanggal_mulai',
        'tanggal_berakhir',
        'status',
        'nama_file',
    ];

    protected $casts = [
        'epoch'            => 'integer',
        'epoch_aktual'     => 'integer',
        'early_stopping'   => 'boolean',
        'patience'         => 'integer',
        'batch_size'       => 'integer',
        'window_size'      => 'integer',
        'horizon'          => 'integer',
        'learning_rate'    => 'float',
        'dropout'          => 'float',
        'jumlah_parameter' => 'integer',
        'durasi_detik'     => 'float',
        'rmse'             => 'float',
        'rmse_validasi'    => 'float',
        'mae'              => 'float',
        'mape'             => 'float',
        'r2'               => 'float',
        'tanggal_evaluasi' => 'datetime',
        'tanggal_mulai'    => 'date',
        'tanggal_berakhir' => 'date',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(ModelPrediksi::class, 'id_model', 'id_model');
    }

    public function modelPrediksi(): BelongsTo
    {
        return $this->belongsTo(ModelPrediksi::class, 'id_model', 'id_model');
    }

    public function komoditas(): BelongsTo
    {
        return $this->belongsTo(Komoditas::class, 'id_komoditas', 'id_komoditas');
    }

    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah', 'id_wilayah');
    }

    public function hasilPrediksi(): HasMany
    {
        return $this->hasMany(HasilPrediksi::class, 'id_evaluasi', 'id_evaluasi');
    }
}