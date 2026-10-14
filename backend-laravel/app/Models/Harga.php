<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Harga extends Model
{
    // Hubungkan model ke tabel di db
    protected $table = 'data_harga'; 
    protected $primaryKey = 'id_harga'; 
    protected $fillable = [
        'id_komoditas',
        'id_wilayah',
        'tanggal',
        'harga',

    ];

    //relasi ke komoditas
    public function komoditas()
    {
        return $this->belongsTo(Komoditas::class, 'id_komoditas', 'id_komoditas');
    }

    //relasi ke wilayah
    public function wilayah()
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah', 'id_wilayah');
    }
}
