<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Hacienda\Hacienda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HaciendaController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show', 'customSelect']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        $haciendas = Hacienda::orderBy('updated_at', 'DESC')->paginate(7);

        if (!is_null($haciendas) && !empty($haciendas) && count($haciendas) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['dataArray'] = $haciendas;
        } else {
            $this->out['message'] = 'No hay datos registrados';
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function customSelect()
    {
        $haciendas = Hacienda::all();

        if (!is_null($haciendas) && !empty($haciendas) && count($haciendas) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['dataArray'] = $haciendas;
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
                    'detalle' =>
                        "required|min:1|max:100|unique:HACIENDAS,detalle,NULL,NULL",
                ],
                [
                    'detalle.unique' => 'La hacienda ' . $params_array['detalle'] . ' ya se encuentra registrada...'
                ]);

            if ($validacion->fails()) {
                $this->out['message'] = "Los datos enviados no son correctos";
                $this->out['error'] = $validacion->errors();
            } else {
                $hacienda = new Hacienda();
                $hacienda->detalle = strtoupper(trim($params_array['detalle']));
                $hacienda->created_at = Carbon::now()->format("d-m-Y H:i:s");
                $hacienda->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                $hacienda->save();

                $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                $this->out['hacienda'] = $hacienda;
            }

        } else {
            $this->out['message'] = "No se han recibido parametros";
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function show($id)
    {
        $hacienda = Hacienda::find($id);

        if (is_object($hacienda) && !empty($hacienda)) {
            $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
            $this->out['hacienda'] = $hacienda;
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function update(Request $request, $id)
    {
        $hacienda = Hacienda::find($id);

        if (is_object($hacienda) && !empty($hacienda)) {
            $json = $request->input('json', null);
            $params = json_decode($json);
            $params_array = json_decode($json, true);

            if (!empty($params_array) && count($params_array) > 0) {
                $validacion = Validator::make($params_array,
                    [
                        'detalle' =>
                            "required|min:1|max:100|unique:HACIENDAS,detalle,NULL,NULL",
                    ],
                    [
                        'detalle.unique' => 'La hacienda ' . $params_array['detalle'] . ' ya se encuentra registrada...'
                    ]);

                if ($validacion->fails()) {
                    $this->out['message'] = "Los datos enviados no son correctos";
                    $this->out['error'] = $validacion->errors();
                } else {
                    $hacienda->detalle = strtoupper(trim($params_array['detalle']));
                    $hacienda->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                    $hacienda->save();

                    $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                    $this->out['hacienda'] = $hacienda;
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
        $delete = false;
        try {
            $hacienda = Hacienda::find($id);
            if (is_object($hacienda) && !empty($hacienda)) {
                Hacienda::destroy($id);
                $this->out = $this->respuesta_json('success', 200, 'Dato eliminado correctamente.');
                return response()->json($this->out, $this->out['code']);
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
