<?php

namespace App;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class EMP_TIPO_CAJA extends BaseModel
{
    protected $table = 'EMP_TIPO_CAJA';

    protected $fillable = [
        'descripcion'
    ];

    protected $hidden = [
        'id', 'estado'
    ];
}
