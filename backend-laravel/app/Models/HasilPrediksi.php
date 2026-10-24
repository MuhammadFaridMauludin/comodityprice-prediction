<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilPrediksi extends Model
{
    protected $table = 'hasil_prediksi';
    protected $primaryKey = 'id_prediksi';
    public $timestamps = false;

    protected $fillable = [
        'id_model',
        'id_komoditas',
        'id_wilayah',
        'id_evaluasi',
        'tanggal_prediksi',
        'harga_prediksi',
        'tanggal_proses',
    ];

    protected $casts = [
        'harga_prediksi'   => 'float',
        'tanggal_prediksi' => 'date',
        'tanggal_proses'   => 'datetime',
    ];

    public function evaluasi(): BelongsTo
    {
        return $this->belongsTo(EvaluasiModel::class, 'id_evaluasi', 'id_evaluasi');
    }

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
}