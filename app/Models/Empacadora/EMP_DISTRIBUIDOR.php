<?php

namespace App\Models\Empacadora;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class EMP_DISTRIBUIDOR extends BaseModel
{
    protected $table = 'EMP_DISTRIBUIDOR';

    protected $fillable = [
        'descripcion'
    ];

    protected $hidden = [
        'id', 'estado'
    ];
}
