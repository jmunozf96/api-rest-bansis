<?php

namespace App\Models\Empacadora;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class EMP_DESTINO extends BaseModel
{
    protected $table = 'EMP_DESTINO';

    protected $fillable = [
        'descripcion', 'continente'
    ];

    protected $hidden = [
        'id', 'estado'
    ];
}
