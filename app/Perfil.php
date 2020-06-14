<?php

namespace App;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Perfil extends BaseModel
{
    protected $table = 'SIS_PERFIL_USUARIOS';

    public function usuario()
    {
        return $this->hasOne('App\User', 'id', 'iduser');
    }

    public function recurso()
    {
        return $this->hasOne('App\Recurso', 'id', 'idrecurso');
    }
}
