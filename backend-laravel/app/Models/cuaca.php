<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuaca extends Model
{
    protected $table = 'data_cuaca'; 
    protected $primaryKey = 'id_cuaca'; 
    public $timestamps = false; // Tambahkan jika tabel tidak memiliki created_at/updated_at

    protected $fillable = [
        'id_koordinat', // wajib dimasukkan agar bisa diisi
        'tanggal',
        'suhu',
        'curah_hujan',
        'kelembapan'
    ];

    // Method relasi gunakan camelCase agar rapi
    public function koordinat()
    {
        return $this->belongsTo(KoordinatCuaca::class, 'id_koordinat', 'id_koordinat');
    }
}