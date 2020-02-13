<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EMP_DESTINO extends Model
{
    protected $table = 'EMP_DESTINO';
    protected $dateFormat = 'd-m-Y H:i:s';

    protected $fillable = [
        'descripcion'
    ];

    protected $hidden = [
        'id'
    ];
}
