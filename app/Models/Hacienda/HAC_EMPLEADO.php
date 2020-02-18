<?php

namespace App\Models\Hacienda;

use Illuminate\Database\Eloquent\Model;

class HAC_EMPLEADO extends Model
{
    protected $table = 'HAC_EMPLEADO';

    protected $fillable = [
        'id'
    ];

    public function egresos()
    {
        $this->hasMany('App\Models\Bodega\BOD_EGRESO', 'idempleado');
    }
}
