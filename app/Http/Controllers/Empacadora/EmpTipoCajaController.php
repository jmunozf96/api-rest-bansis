<?php

namespace App\Http\Controllers\Empacadora;

use App\Models\Empacadora\EMP_TIPO_CAJA;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class EmpTipoCajaController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        $tipos_caja = EMP_TIPO_CAJA::all()->load(['cajas']);

        if (!is_null($tipos_caja) && !empty($tipos_caja) && count($tipos_caja) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['tipos_caja'] = $tipos_caja;
        } else {
            $this->out['message'] = 'No hay datos registrados';
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function store(Request $request)
    {
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);

        if (!empty($params_array) && count($params_array) > 0) {
            $validacion = Validator::make($params_array, [
                'descripcion' => 'required|unique:EMP_TIPO_CAJA,descripcion'
            ]);

            if ($validacion->fails()) {
                $this->out['message'] = "Los datos enviados no son correctos";
                $this->out['error'] = $validacion->errors();
            } else {
                $tipo_caja = new EMP_TIPO_CAJA();
                $tipo_caja->descripcion = $params_array['descripcion'];
                $tipo_caja->save();

                $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                $this->out['tipo_caja'] = $tipo_caja;
            }

        } else {
            $this->out['message'] = "No se han recibido parametros";
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function show($id)
    {
        $tipo_caja = EMP_TIPO_CAJA::find($id);

        if (is_object($tipo_caja) && !empty($tipo_caja)) {
            $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
            $this->out['tipo_caja'] = $tipo_caja;
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function update(Request $request, $id)
    {
        $tipo_caja = EMP_TIPO_CAJA::find($id);

        if (is_object($tipo_caja) && !empty($tipo_caja)) {

            $json = $request->input('json');
            $params_array = json_decode($json, true);

            $validacion = Validator::make($params_array, [
                'descripcion' => 'required'
            ]);

            if ($validacion->fails()) {
                $this->out['message'] = "Los datos enviados no son correctos";
                $this->out['error'] = $validacion->errors();
            } else {
                unset($params_array['id']);
                unset($params_array['created_at']);

                $tipo_caja->descripcion = $params_array['descripcion'];
                $tipo_caja->save();

                $this->out = $this->respuesta_json('success', 200, 'Datos actualizados correctamente');
                $this->out['tipo_caja'] = $tipo_caja;
            }

        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function destroy($id)
    {
        $tipo_caja = EMP_TIPO_CAJA::find($id);

        if (is_object($tipo_caja) && !empty($tipo_caja)) {
            $tipo_caja->delete();
            $this->out = $this->respuesta_json('success', 200, 'Dato eliminado correctamente.');
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
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
