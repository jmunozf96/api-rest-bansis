<?php

namespace App\Models\Sisban\Cosecha;

use App\Models\BaseModel;

class Danos extends BaseModel
{
    protected $table = "HAC_DANOS";
    
    protected $hidden = [
        'created_at', 'updated_at', 'estado'
    ];
}
