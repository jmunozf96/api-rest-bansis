<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Hacienda\Labor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LaborController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show', 'customSelect']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        $labores = Labor::orderBy('updated_at', 'DESC')->paginate(7);

        if (!is_null($labores) && !empty($labores) && count($labores) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['dataArray'] = $labores;
        } else {
            $this->out['message'] = 'No hay datos registrados';
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function customSelect()
    {
        $labores = Labor::all();

        if (!is_null($labores) && !empty($labores) && count($labores) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['dataArray'] = $labores;
        } else {
            $this->out['message'] = 'No hay datos registrados';
        }

        return response()->json($this->out, $this->out['code']);
    }


    public function store(Request $request)
    {
        $json = $request->input('json', null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if (!empty($params_array) && count($params_array) > 0) {
            $validacion = Validator::make($params_array,
                [
                    'descripcion' =>
                        "required|min:1|max:100|unique:HAC_LABORES,descripcion,NULL,NULL",
                ],
                [
                    'descripcion.unique' => 'La labor de ' . $params_array['descripcion'] . ' ya se encuentra registrada...'
                ]);

            if ($validacion->fails()) {
                $this->out['message'] = "Los datos enviados no son correctos";
                $this->out['error'] = $validacion->errors();
            } else {
                $labor = new Labor();
                $labor->descripcion = strtoupper(trim($params_array['descripcion']));
                $labor->created_at = Carbon::now()->format(config('constants.format_date'));
                $labor->updated_at = Carbon::now()->format(config('constants.format_date'));
                $labor->save();

                $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                $this->out['labor'] = $labor;
            }

        } else {
            $this->out['message'] = "No se han recibido parametros";
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function show($id)
    {
        $labor = Labor::find($id);

        if (is_object($labor) && !empty($labor)) {
            $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
            $this->out['labor'] = $labor;
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function update(Request $request, $id)
    {
        $labor = Labor::find($id);

        if (is_object($labor) && !empty($labor)) {
            $json = $request->input('json', null);
            $params = json_decode($json);
            $params_array = json_decode($json, true);

            if (!empty($params_array) && count($params_array) > 0) {
                $validacion = Validator::make($params_array,
                    [
                        'descripcion' =>
                            "required|min:1|max:100|unique:HAC_LABORES,descripcion,NULL,NULL",
                    ],
                    [
                        'descripcion.unique' => 'La labor de ' . $params_array['descripcion'] . ' ya se encuentra registrada...'
                    ]);

                if ($validacion->fails()) {
                    $this->out['message'] = "Los datos enviados no son correctos";
                    $this->out['error'] = $validacion->errors();
                } else {
                    $labor->descripcion = strtoupper(trim($params_array['descripcion']));
                    $labor->updated_at = Carbon::now()->format(config('constants.format_date'));
                    $labor->save();

                    $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                    $this->out['labor'] = $labor;
                }

            } else {
                $this->out['message'] = "No se han recibido parametros";
            }
        } else {
            $this->out['message'] = "No existen datos con el parametro enviado.";
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function destroy($id)
    {
        $labor = Labor::find($id);
        try {
            if (is_object($labor) && !empty($labor)) {
                $labor->delete();
                $this->out = $this->respuesta_json('success', 200, 'Dato eliminado correctamente.');
            } else {
                $this->out['message'] = 'No existen datos con el parametro enviado.';
            }
        } catch (\Exception $e) {
            $this->out['code'] = 500;
            $this->out['message'] = 'No se puede eliminar el registro, conflicto en la base de datos, por favor contactar con el administrador del sistema.';
            $this->out['error_message'] = $e->getMessage();
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
