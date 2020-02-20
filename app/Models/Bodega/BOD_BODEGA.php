<?php

namespace App\Models\Bodega;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class BOD_BODEGA extends BaseModel
{
    protected $table = 'BOD_BODEGAS';



    public function materiales()
    {
        return $this->hasMany('App\Models\Bodega\BOD_MATERIAL', 'idbodega');
    }
}
