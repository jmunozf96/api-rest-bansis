<?php

namespace App\Models\Bodega;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Material extends BaseModel
{
    protected $table = "BOD_MATERIALES";

    protected $hidden = [
        'idbodega', 'idgrupo'
    ];

    public function getBodega()
    {
        return $this->hasOne('App\Models\Bodega\Bodega', 'id', 'idbodega');
    }

    public function getGrupo()
    {
        return $this->hasOne('App\Models\Bodega\Grupo', 'id', 'idgrupo');
    }

}
