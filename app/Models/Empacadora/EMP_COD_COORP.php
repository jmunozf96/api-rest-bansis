<?php

namespace App\Models\Empacadora;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class EMP_COD_COORP extends BaseModel
{
    protected $table = 'EMP_COD_COORP';

    protected $fillable = [
        'descripcion', 'id_caja'
    ];

    protected $hidden = [
        'id', 'estado', 'id_caja'
    ];

    public function caja()
    {
        return $this->belongsTo('App\Models\Empacadora\EMP_CAJA', 'id_caja')
            ->select('id', 'descripcion', 'peso_max', 'peso_min', 'peso_standard', 'id_destino', 'id_tipoCaja', 'id_distrib')
            ->with(['destino', 'tipo_caja', 'distribuidor']);
    }
}
