<?php

namespace App\Models\Bodega;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class BOD_GRUPO extends BaseModel
{
    protected $table = 'BOD_GRUPO';

    protected $hidden = [
        'id'
    ];

    public function materiales()
    {
        return $this->hasMany('App\Models\Bodega\BOD_MATERIAL', 'idmaterial', 'id');
    }
}
