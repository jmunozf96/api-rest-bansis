<?php

namespace App\Models\Bodega;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class BOD_MATERIAL extends BaseModel
{

    protected $table = 'BOD_MATERIAL';

    protected $hidden = [
        'id'
    ];

    public function bodega()
    {
        $this->belongsTo('App\Models\Bodega\BOD_BODEGA', 'idbodega');
    }

    public function grupo()
    {
        $this->belongsTo('App\Models\Bodega\BOD_GRUPO', 'idgrupo');
    }
}
