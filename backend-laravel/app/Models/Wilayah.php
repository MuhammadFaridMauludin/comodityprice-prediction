<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wilayah extends Model
{
    // 1. Tentukan nama tabel asli yang ada di DBeaver/Database Anda
    protected $table = 'wilayah'; // <-- Ubah sesuai nama tabel di DBeaver (contoh: 'wilayah', 'data_wilayah', dll)

    // 2. Tentukan Primary Key jika bukan 'id'
    protected $primaryKey = 'id_wilayah';

    // 3. Matikan timestamps jika tabel tidak memiliki kolom created_at & updated_at
    public $timestamps = false;
}
