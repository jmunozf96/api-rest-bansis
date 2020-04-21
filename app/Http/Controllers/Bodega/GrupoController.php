<?php

namespace App\Http\Controllers\Bodega;

use App\Http\Controllers\Controller;
use App\Models\Bodega\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GrupoController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show', 'customSelect']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        try {
            $grupos = Grupo::orderBy('updated_at', 'DESC')->paginate(7);

            if (!is_null($grupos) && !empty($grupos) && count($grupos) > 0) {
                $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
                $this->out['dataArray'] = $grupos;
            } else {
                $this->out['message'] = 'No hay datos registrados';
            }
        } catch (\Exception $exception) {
            $this->out['message'] = $exception->getMessage();
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function store(Request $request)
    {
        try {
            $json = $request->input('json', null);
            $params = json_decode($json);
            $params_array = json_decode($json, true);

            if (!empty($params_array) && count($params_array) > 0) {
                $validacion = Validator::make($params_array,
                    [
                        'descripcion' =>
                            "required|min:1|max:300|unique:BOD_GRUPOS,descripcion,NULL,NULL",
                    ],
                    [
                        'descripcion.unique' => 'El grupo ' . $params_array['descripcion'] . ' ya se encuentra registrado...'
                    ]);

                if ($validacion->fails()) {
                    $this->out['message'] = "Los datos enviados no son correctos";
                    $this->out['error'] = $validacion->errors();
                } else {
                    $grupo = new Grupo();
                    $grupo->descripcion = strtoupper($params_array['descripcion']);
                    $grupo->save();

                    $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                    $this->out['grupo'] = $grupo;
                }

            } else {
                $this->out['message'] = "No se han recibido parametros";
            }
        } catch (\Exception $exception) {
            $this->out['message'] = $exception->getMessage();
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function show($id)
    {
        $grupo = Grupo::find($id);

        if (is_object($grupo) && !empty($grupo)) {
            $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
            $this->out['grupo'] = $grupo;
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function update(Request $request, $id)
    {
        try {
            $grupo = Grupo::find($id);

            if (is_object($grupo) && !empty($grupo)) {
                $json = $request->input('json', null);
                $params = json_decode($json);
                $params_array = json_decode($json, true);

                if (!empty($params_array) && count($params_array) > 0) {
                    $validacion = Validator::make($params_array,
                        [
                            'descripcion' =>
                                "required|min:1|max:300|unique:BOD_GRUPOS,descripcion,NULL,NULL",
                        ],
                        [
                            'descripcion.unique' => 'El grupo ' . $params_array['descripcion'] . ' no se ha modificado...'
                        ]);

                    if ($validacion->fails()) {
                        $this->out['message'] = "Los datos enviados no son correctos";
                        $this->out['error'] = $validacion->errors();
                    } else {
                        $grupo->descripcion = strtoupper($params_array['descripcion']);
                        $grupo->save();

                        $this->out = $this->respuesta_json('success', 200, 'Datos actualizados correctamente');
                        $this->out['grupo'] = $grupo;
                    }

                } else {
                    $this->out['message'] = "No se han recibido parametros.";
                }
            } else {
                $this->out['message'] = "No existen datos con el parametro enviado.";
            }
        } catch (\Exception $exception) {
            $this->out['message'] = $exception->getMessage();
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function destroy($id)
    {
        try {
            $grupos = Grupo::find($id);

            if (is_object($grupos) && !empty($grupos)) {
                $grupos->delete();
                $this->out = $this->respuesta_json('success', 200, 'Dato eliminado correctamente.');
            } else {
                $this->out['message'] = 'No existen datos con el parametro enviado.';
            }
            return response()->json($this->out, $this->out['code']);
        } catch (\Exception $e) {
            $this->out['code'] = 500;
            $this->out['message'] = 'No se puede eliminar el registro, conflicto en la base de datos, por favor contactar con el administrador del sistema.';
            $this->out['error_message'] = $e->getMessage();
        }
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
