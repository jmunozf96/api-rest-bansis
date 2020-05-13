<?php

namespace App\Models\Hacienda;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Lote extends BaseModel
{
    protected $table = 'HAC_LOTES';

    public function hacienda()
    {
        return $this->hasOne('App\Models\Hacienda\Hacienda', 'id', 'idhacienda');
    }
}
