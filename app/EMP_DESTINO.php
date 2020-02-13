<?php

namespace App;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class EMP_DESTINO extends BaseModel
{
    protected $table = 'EMP_DESTINO';

    protected $fillable = [
        'descripcion'
    ];

    protected $hidden = [
        'id', 'estado'
    ];
}
