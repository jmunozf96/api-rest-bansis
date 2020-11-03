<?php

namespace App\Models\Sistema;

use App\Models\BaseModel;
use App\Models\Hacienda\Hacienda;
use App\Models\Hacienda\LoteSeccion;
use App\Models\Sisban\Cosecha\Danos;
use Illuminate\Database\Eloquent\Model;

class ManosRecusadas extends BaseModel
{
    protected $table = "HAC_COSECHA_MANOS_REC";

    protected $hidden = ['dano_des', 'idlote'];

    public function lote()
    {
        return $this->hasOne(LoteSeccion::class, 'id', 'idlote');
    }

    public function hacienda()
    {
        return $this->hasOne(Hacienda::class, 'id', 'idhacienda');
    }

    public function calendario()
    {
        return $this->hasOne(Calendario::class, 'fecha', 'fecha');
    }

    public function dano()
    {
        return $this->hasOne(Danos::class, 'nombre', 'dano_des');
    }
}
