<?php

namespace App\Http\Controllers\Bodega;

use App\Http\Controllers\Controller;
use App\Models\Bodega\Bodega;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BodegaController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show', 'customSelect', 'getOptions']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        try {
            $bodegas = Bodega::with('hacienda')->orderBy('updated_at', 'DESC')->paginate(7);

            if (!is_null($bodegas) && !empty($bodegas) && count($bodegas) > 0) {
                $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
                $this->out['dataArray'] = $bodegas;
            } else {
                $this->out['message'] = 'No hay datos registrados';
            }
        } catch (\Exception $exception) {
            $this->out['message'] = $exception->getMessage();
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function getOptions()
    {
        $bodegas = Bodega::select('id', 'nombre as descripcion', 'idhacienda')->with('hacienda')->get();
        return response()->json($bodegas, 200);
    }

    public function customSelect()
    {
        $bodegas = Bodega::select('id','nombre as descripcion')->get();

        if (!is_null($bodegas) && !empty($bodegas) && count($bodegas) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['dataArray'] = $bodegas;
        } else {
            $this->out['message'] = 'No hay datos registrados';
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
                        'nombre' =>
                            "required|min:1|max:300|unique:BOD_BODEGAS,nombre,NULL,NULL,idhacienda,$params->idhacienda",
                        'idhacienda' => 'required'
                    ],
                    [
                        'nombre.unique' => 'La bodega ' . $params_array['nombre'] . ' ya se encuentra registrada...'
                    ]);

                if ($validacion->fails()) {
                    $this->out['message'] = "Los datos enviados no son correctos";
                    $this->out['error'] = $validacion->errors();
                } else {
                    $bodega = new Bodega();
                    $bodega->idhacienda = $params_array['idhacienda'];
                    $bodega->nombre = strtoupper($params_array['nombre']);
                    $bodega->descripcion = strtoupper($params_array['descripcion']);
                    $bodega->created_at = Carbon::now()->format(config('constants.format_date'));
                    $bodega->updated_at = Carbon::now()->format(config('constants.format_date'));
                    $bodega->save();

                    $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                    $this->out['bodega'] = $bodega;
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
        $bodega = Bodega::find($id);

        if (is_object($bodega) && !empty($bodega)) {
            $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
            $this->out['bodega'] = $bodega->load('hacienda');
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function update(Request $request, $id)
    {
        try {
            $bodega = Bodega::find($id);

            if (is_object($bodega) && !empty($bodega)) {
                $json = $request->input('json', null);
                $params = json_decode($json);
                $params_array = json_decode($json, true);

                if (!empty($params_array) && count($params_array) > 0) {
                    $validacion = Validator::make($params_array,
                        [
                            'nombre' =>
                                "required|min:1|max:300|unique:BOD_BODEGAS,nombre,NULL,NULL,idhacienda,$params->idhacienda,descripcion,$params->descripcion",
                            'idhacienda' => 'required'
                        ],
                        [
                            'nombre.unique' => 'La bodega ' . $params_array['nombre'] . ' no ha sido afectada, edicion no exitosa...'
                        ]);


                    if ($validacion->fails()) {
                        $this->out['message'] = "Los datos enviados no son correctos";
                        $this->out['error'] = $validacion->errors();
                    } else {
                        unset($params_array['id']);
                        unset($params_array['created_at']);

                        $bodega->idhacienda = $params_array['idhacienda'];
                        $bodega->nombre = strtoupper($params_array['nombre']);
                        $bodega->descripcion = strtoupper($params_array['descripcion']);
                        $bodega->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $bodega->save();

                        $this->out = $this->respuesta_json('success', 200, 'Datos actualizados correctamente');
                        $this->out['bodega'] = $bodega;
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
            $bodega = Bodega::find($id);

            if (is_object($bodega) && !empty($bodega)) {
                $bodega->delete();
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
