<?php

namespace App\Models\Empacadora;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class EMP_DESTINO extends BaseModel
{
    protected $table = 'EMP_DESTINO';

    protected $fillable = [
        'descripcion', 'continente'
    ];

    protected $hidden = [
        'id', 'estado'
    ];

    public function cajas()
    {
        return $this->hasMany('App\Models\Empacadora\EMP_CAJA', 'id_destino')
            ->select('id', 'id_destino', 'id_distrib', 'id_tipoCaja', 'descripcion',
                'peso_max', 'peso_min', 'peso_standard', 'id_codAllweights')
            ->with(['distribuidor', 'tipo_caja']);
    }
}
