<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Komoditas extends Model
{
    //Hubungkan model ke tabel di db
    protected $table = 'komoditas';
    protected $primaryKey = 'id_komoditas';
    //karena tidak memiliki created_at & updated_at
    public $timestamps = false;
}
