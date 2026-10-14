<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KoordinatCuaca extends Model
{
     //Hubungkan model ke tabel di db
    protected $table = 'koordinat_cuaca';
    protected $primaryKey = 'id_koordinat';
    //karena tidak memiliki created_at & updated_at
    public $timestamps = false;
    protected $fillable = [
        'nama_koordinat',
        'latitude',
        'longitude',
    ];
}