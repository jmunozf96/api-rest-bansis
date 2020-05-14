<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Hacienda\Lote;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoteController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show', 'customSelect']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index(Request $request)
    {
        $hacienda = $request->get('hacienda');
        $lotes = Lote::selectRaw("id, idhacienda, right('0' + identificacion,2) as identificacion,has,descripcion,latitud,longitud,updated_at,estado")->with('hacienda');

        if (!empty($hacienda) && isset($hacienda))
            $lotes = $lotes->where('idhacienda', $hacienda)->orderByRaw("right('0' + identificacion,2)", 'asc');

        $lotes = $lotes->orderBy('updated_at', 'DESC')
            ->paginate(7);

        if (!is_null($lotes) && !empty($lotes) && count($lotes) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['dataArray'] = $lotes;
        } else {
            $this->out['message'] = 'No hay datos registrados';
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function customSelect(Request $request)
    {
        $hacienda = $request->get('hacienda');

        if (!empty($hacienda)) {
            $haciendas = Lote::selectRaw("id, identificacion,(descripcion + ' - has: ' + CONVERT(varchar, has)) as descripcion, has")->where('idhacienda', $hacienda)->get();
            if (!is_null($haciendas) && !empty($haciendas) && count($haciendas) > 0) {
                $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
                $this->out['dataArray'] = $haciendas;
            } else {
                $this->out['message'] = 'No hay datos registrados';
            }
        } else {
            $this->out['message'] = 'No se ha recibido parametro de hacienda';
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function store(Request $request)
    {
        try {
            $json = $request->input('json');
            $params = json_decode($json);
            $params_array = json_decode($json, true);

            if (is_object($params) && !empty($params)) {
                $validacion = Validator::make($params_array, [
                    'hacienda' => 'required',
                    'lote' => 'required',
                    'has' => 'required',
                    'latitud' => 'required',
                    'longitud' => 'required'
                ]);

                if (!$validacion->fails()) {
                    $existe = Lote::where(['idhacienda' => $params_array['hacienda']['id'], 'identificacion' => strtoupper(trim($params_array['lote']))])->first();
                    if (!is_object($existe)) {
                        $lote = new Lote();
                        $lote->idhacienda = $params_array['hacienda']['id'];
                        $lote->identificacion = strtoupper(trim($params_array['lote']));
                        $lote->has = floatval($params_array['has']);
                        $lote->descripcion = strtoupper(trim($params_array['detalle']));
                        $lote->latitud = floatval($params_array['latitud']);
                        $lote->longitud = floatval($params_array['longitud']);
                        $lote->created_at = Carbon::now()->format(config('constants.format_date'));
                        $lote->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $result = $lote->save();

                        if ($result) {
                            $this->out = $this->respuesta_json('success', 200, 'Datos registrados correctamente');
                            return response()->json($this->out, 200);
                        }

                    } else {
                        return $this->update($request);
                    }
                    throw new \Exception('No se puedo guardar la información', 500);

                } else {
                    $this->out['errors'] = $validacion->errors()->all();
                    throw new \Exception('Error en la validacion de los datos', 500);
                }
            }
            throw new \Exception('No se han recibido parametros', 500);
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function show($id)
    {
        $lote = Lote::where('id', $id)
            ->with(['hacienda' => function ($query) {
                $query->select('id', 'detalle as descripcion', 'ruc');
            }])->first();

        if (!is_null($lote) && !empty($lote)) {
            $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
            $this->out['lote'] = $lote;
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function update(Request $request)
    {
        try {
            $json = $request->input('json');
            $params = json_decode($json);
            $params_array = json_decode($json, true);

            if (is_object($params) && !empty($params)) {
                $validacion = Validator::make($params_array, [
                    'hacienda' => 'required',
                    'lote' => 'required',
                    'has' => 'required',
                    'latitud' => 'required',
                    'longitud' => 'required'
                ]);

                if (!$validacion->fails()) {
                    $lote = Lote::where(['idhacienda' => $params_array['hacienda']['id'], 'identificacion' => strtoupper(trim($params_array['lote']))])->first();
                    if (is_object($lote)) {
                        $lote->identificacion = strtoupper(trim($params_array['lote']));
                        $lote->has = floatval($params_array['has']);
                        $lote->descripcion = strtoupper(trim($params_array['detalle']));
                        $lote->latitud = floatval($params_array['latitud']);
                        $lote->longitud = floatval($params_array['longitud']);
                        $lote->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $result = $lote->update();

                        if ($result) {
                            $this->out = $this->respuesta_json('success', 200, 'Datos actualizados correctamente');
                            return response()->json($this->out, 200);
                        }
                    }

                    throw new \Exception('No se puedo guardar la información', 500);

                } else {
                    $this->out['errors'] = $validacion->errors()->all();
                    throw new \Exception('Error en la validacion de los datos', 500);
                }
            }
            throw new \Exception('No se han recibido parametros', 500);
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    function destroy($id)
    {
        $delete = false;
        try {
            $lote = Lote::find($id);
            if (is_object($lote) && !empty($lote)) {
                Lote::destroy($id);
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
