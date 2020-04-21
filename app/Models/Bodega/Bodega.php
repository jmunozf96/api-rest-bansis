<?php

namespace App\Models\Bodega;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Bodega extends BaseModel
{
    protected $table = "BOD_BODEGAS";

    public function hacienda()
    {
        return $this->hasOne('App\Models\Hacienda\Hacienda', 'id', 'idhacienda');
    }
}
