<?php

namespace App\Models\Empacadora;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class EMP_DISTRIBUIDOR extends BaseModel
{
    protected $table = 'EMP_DISTRIBUIDOR';

    protected $fillable = [
        'descripcion'
    ];

    protected $hidden = [
        'id', 'estado'
    ];

    public function cajas()
    {
        return $this->hasMany('App\Models\Empacadora\EMP_CAJA', 'id_distrib')
            ->select('id', 'id_destino', 'id_distrib', 'id_tipoCaja', 'descripcion',
                'peso_max', 'peso_min', 'peso_standard', 'id_codAllweights')
            ->with(['tipo_caja', 'destino']);
    }
}
