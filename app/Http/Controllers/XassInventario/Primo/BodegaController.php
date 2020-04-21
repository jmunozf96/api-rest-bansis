<?php

namespace App\Http\Controllers\XassInventario\Primo;

use App\Http\Controllers\Controller;
use App\Models\XassInventario\Primo\Bodega;
use Illuminate\Http\Request;

class BodegaController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function getBodegas()
    {
        try {
            $bodegas = Bodega::select('Id_Fila', 'Nombre as descripcion')->get();
            $this->out['dataArray'] = $bodegas;
        } catch (\Exception $exception) {
            $this->out['message'] = $exception->getMessage();
        }

        return response()->json($this->out, 200);
    }

    public function respuesta_json(...$datos)
    {
        return array(
            'status' => $datos[0],
            'code' => $datos[1],
            'message' => $datos[2]
        );
    }
}
