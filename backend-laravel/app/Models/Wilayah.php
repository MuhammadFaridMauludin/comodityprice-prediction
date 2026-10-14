<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wilayah extends Model
{
    //Hubungkan model ke tabel di db
    protected $table = 'wilayah';
    protected $primaryKey = 'id_wilayah';
    //karena tidak memiliki created_at & updated_at
    public $timestamps = false;
}
