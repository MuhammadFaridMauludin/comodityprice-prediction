<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Harga extends Model
{
    // Hubungkan model ke nama tabel yang ada di DBeaver
    protected $table = 'data_harga'; 

    // Jika primary key Anda bukan 'id', tentukan di sini
    protected $primaryKey = 'id_harga'; 

    // Masukkan nama kolom sesuai yang dibuat di DBeaver
    protected $fillable = [
        'tanggal',
        'harga',

    ];

    // 5. Relasi ke tabel Komoditas (sesuaikan nama Model Komoditas Anda)
    public function komoditas()
    {
        return $this->belongsTo(Komoditas::class, 'id_komoditas', 'id_komoditas');
    }

    // 6. Relasi ke tabel Wilayah (sesuaikan nama Model Wilayah Anda)
    public function wilayah()
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah', 'id_wilayah');
    }
}
