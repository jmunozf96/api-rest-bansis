<?php

namespace App\Models\Hacienda;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Enfunde extends BaseModel
{
    protected $table = 'HAC_ENFUNDES';

    public function detalle()
    {
        return $this->hasMany('App\Models\Hacienda\EnfundeDet', 'idenfunde', 'id');
    }

    public function hacienda()
    {
        return $this->hasOne('App\Models\Hacienda\Hacienda', 'id', 'idhacienda');
    }

    public function labor()
    {
        return $this->hasOne('App\Models\Hacienda\Labor', 'id', 'idlabor');
    }
}
