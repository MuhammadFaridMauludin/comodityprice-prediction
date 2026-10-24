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
        'learning_rate',
        'jumlah_parameter',
        'tanggal_evaluasi',
        'rmse',
        'mae',
        'mape',
        'tanggal_mulai',
        'tanggal_berakhir',
        'status',
    ];

    protected $casts = [
        'epoch'            => 'integer',
        'epoch_aktual'     => 'integer',
        'early_stopping'   => 'boolean',
        'patience'         => 'integer',
        'batch_size'       => 'integer',
        'window_size'      => 'integer',
        'learning_rate'    => 'float',
        'jumlah_parameter' => 'integer',
        'rmse'             => 'float',
        'mae'              => 'float',
        'mape'             => 'float',
        'tanggal_evaluasi' => 'datetime',
        'tanggal_mulai'    => 'date',
        'tanggal_berakhir' => 'date',
    ];

    public function model(): BelongsTo
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