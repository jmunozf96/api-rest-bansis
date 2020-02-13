<?php

namespace App\Models\Empacadora;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class EMP_CAJA extends BaseModel
{
    protected $table = 'EMP_CAJAS';

    protected $fillable = [
        'descripcion', 'peso_max', 'peso_min', 'peso_standard'
    ];

    protected $hidden = [
        'id', 'estado', 'id_destino', 'id_distrib', 'id_tipoCaja'
    ];

    public function destino()
    {
        return $this->belongsTo('App\Models\Empacadora\EMP_DESTINO', 'id_destino')
            ->select('id', 'descripcion', 'continente');
    }

    public function distribuidor()
    {
        return $this->belongsTo('App\Models\Empacadora\EMP_DISTRIBUIDOR', 'id_distrib')
            ->select('id', 'descripcion');
    }

    public function tipo_caja()
    {
        return $this->belongsTo('App\Models\Empacadora\EMP_TIPO_CAJA', 'id_tipoCaja')
            ->select('id', 'descripcion');
    }

    public function cod_coorporativo()
    {
        return $this->hasMany('App\Models\Empacadora\EMP_COD_COORP', 'id_caja')
            ->select('id', 'id_caja', 'descripcion');
    }
}
