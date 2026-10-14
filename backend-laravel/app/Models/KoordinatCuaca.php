<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KoordinatCuaca extends Model
{
    protected $table = 'koordinat_cuaca';
    protected $primaryKey = 'id_koordinat';
    public $timestamps = false;

    protected $fillable = [
        'nama_koordinat', // Sesuaikan dengan kolom nama lokasi/wilayah di DBeaver Anda
        'latitude',
        'longitude',
    ];
}