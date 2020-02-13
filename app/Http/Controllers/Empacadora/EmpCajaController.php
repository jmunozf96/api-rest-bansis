<?php

namespace App\Http\Controllers\Empacadora;

use App\Http\Controllers\Controller;
use App\Models\Empacadora\EMP_CAJA;
use Illuminate\Http\Request;

class EmpCajaController extends Controller
{

    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        $cajas = EMP_CAJA::all()->load(['destino', 'distribuidor', 'tipo_caja']);

        if (count($cajas) > 0 && !empty($cajas)) {

        } else {

        }

        return response()->json($cajas, 200);
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
