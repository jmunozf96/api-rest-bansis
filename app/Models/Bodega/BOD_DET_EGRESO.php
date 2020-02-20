<?php

namespace App\Models\Bodega;

use Illuminate\Database\Eloquent\Model;

class BOD_DET_EGRESO extends Model
{
    protected $table = 'BOD_DET_EGRESOS';

    protected $hidden = [
        'id'
    ];

    public function egreso()
    {
        $this->belongsTo('App\Models\Bodega\BOD_EGRESO', 'idegreso');
    }

    public function material()
    {
        $this->belongsTo('App\Models\Bodega\BOD_MATERIAL', 'idmaterial');
    }
}
