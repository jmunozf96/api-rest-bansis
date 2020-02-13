<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EMP_DISTRIBUIDOR extends Model
{
    protected $table = 'EMP_DISTRIBUIDOR';

    protected $fillable = [
        'descripcion'
    ];

    protected $hidden = [
        'id', 'estado'
    ];
}
