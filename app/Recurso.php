<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Recurso extends Model
{
    protected $table = 'SIS_RECURSOS';

    public function recursoHijo()
    {
        return $this->hasMany('App\Recurso', 'padreId', 'id');
    }
}
