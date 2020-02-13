<?php

namespace App\Http\Controllers\Empacadora;

use App\Models\Empacadora\EMP_DISTRIBUIDOR;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class EmpDistribuidorController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        $distribuidores = EMP_DISTRIBUIDOR::all()->load(['cajas']);

        if (!is_null($distribuidores) && !empty($distribuidores) && count($distribuidores) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['distribuidores'] = $distribuidores;
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
                'descripcion' => 'required|unique:EMP_DISTRIBUIDOR,descripcion'
            ]);

            if ($validacion->fails()) {
                $this->out['message'] = "Los datos enviados no son correctos";
                $this->out['error'] = $validacion->errors();
            } else {
                $distribuidor = new EMP_DISTRIBUIDOR();
                $distribuidor->descripcion = $params_array['descripcion'];
                $distribuidor->save();

                $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                $this->out['distribuidor'] = $distribuidor;
            }

        } else {
            $this->out['message'] = "No se han recibido parametros";
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function show($id)
    {
        $distribuidor = EMP_DISTRIBUIDOR::find($id);

        if (is_object($distribuidor) && !empty($distribuidor)) {
            $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
            $this->out['distribuidor'] = $distribuidor;
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function update(Request $request, $id)
    {
        $distribuidor = EMP_DISTRIBUIDOR::find($id);

        if (is_object($distribuidor) && !empty($distribuidor)) {

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

                $distribuidor->descripcion = $params_array['descripcion'];
                $distribuidor->save();

                $this->out = $this->respuesta_json('success', 200, 'Datos actualizados correctamente');
                $this->out['distribuidor'] = $distribuidor;
            }

        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function destroy($id)
    {
        $distribuidor = EMP_DISTRIBUIDOR::find($id);

        if (is_object($distribuidor) && !empty($distribuidor)) {
            $distribuidor->delete();
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
