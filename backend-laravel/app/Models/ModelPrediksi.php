<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelPrediksi extends Model
{
    protected $table = 'model';
    protected $primaryKey = 'id_model';
    public $timestamps = false;
}