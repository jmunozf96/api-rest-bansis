<?php

namespace App\Models\Empacadora;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class EMP_TIPO_CAJA extends BaseModel
{
    protected $table = 'EMP_TIPO_CAJA';

    protected $fillable = [
        'descripcion'
    ];

    protected $hidden = [
        'id', 'estado'
    ];

    public function cajas()
    {
        return $this->hasMany('App\Models\Empacadora\EMP_CAJA', 'id_tipoCaja')
            ->select('id', 'id_destino', 'id_distrib', 'id_tipoCaja', 'descripcion',
                'peso_max', 'peso_min', 'peso_standard', 'id_codAllweights')
            ->with(['distribuidor', 'destino']);
    }
}
