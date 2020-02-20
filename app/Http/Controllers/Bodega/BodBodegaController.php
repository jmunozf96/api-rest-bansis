<?php

namespace App\Http\Controllers\Bodega;

use App\Http\Controllers\Controller;
use App\Models\Bodega\BOD_BODEGA;
use Illuminate\Http\Request;

class BodBodegaController extends Controller
{
    private $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        $bodegas = BOD_BODEGA::all()->load(['materiales']);

        if (!is_null($bodegas) && !empty($bodegas) && count($bodegas) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['bodegas'] = $bodegas;
        } else {
            $this->out['message'] = 'No hay datos registrados';
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function store(Request $request)
    {
        //
    }


    public function show($id)
    {
        //
    }


    public function update(Request $request, $id)
    {
        //
    }


    public function destroy($id)
    {
        //
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
