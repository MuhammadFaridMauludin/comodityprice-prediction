<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuaca extends Model
{
    // Hubungkan model ke tabel di db
    protected $table = 'data_cuaca';
    protected $primaryKey = 'id_cuaca';
    //karena tidak memiliki created_at & updated_at
    public $timestamps = false;

    protected $fillable = [
        'id_koordinat',
        'tanggal',
        'suhu',
        'curah_hujan',
        'kelembapan'
    ];

    //relasi ke koordinat
    public function koordinat()
    {
        return $this->belongsTo(KoordinatCuaca::class, 'id_koordinat', 'id_koordinat');
    }
}