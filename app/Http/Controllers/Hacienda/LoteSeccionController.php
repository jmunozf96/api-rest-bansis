<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Hacienda\LoteSeccion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use mysql_xdevapi\Exception;

class LoteSeccionController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show', 'customSelect']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }


    public function index()
    {
        //
    }


    public function store(Request $request)
    {
        try {
            $json = $request->input('json');
            $params = json_decode($json);
            $params_array = json_decode($json, true);

            if (is_object($params) && !empty($params) && count($params_array) > 0) {
                $validacion = Validator::make($params_array, [
                    'lote' => 'required',
                    'distribucion_lote' => 'required|array',
                    'distribucion_lote.*' => 'required'
                ]);

                DB::beginTransaction();
                $status = false;
                if (!$validacion->fails()) {
                    foreach ($params_array['distribucion_lote'] as $seccion):
                        $timestamp = strtotime(str_replace('/', '-', $seccion['fechaSiembra']));
                        $seccion['fechaSiembra'] = date(config('constants.date'), $timestamp);
                        //Existe lote
                        if (isset($seccion['idDistribucion'])) {
                            $seccion_lote = LoteSeccion::where(['id' => $seccion['idDistribucion']])->first();
                        } else {
                            $seccion_lote = new LoteSeccion();
                            $seccion_lote->idlote = $params_array['lote']['id'];
                            $seccion_lote->descripcion = strtoupper(trim($seccion['descripcion']));
                            $seccion_lote->alias = trim($params_array['lote']['identificacion']) . $seccion_lote->descripcion;
                            $seccion_lote->has = $seccion['has'];
                            $seccion_lote->created_at = Carbon::now()->format(config('constants.format_date'));
                        }

                        $seccion_lote->fecha_siembra = $seccion['fechaSiembra'];
                        $seccion_lote->variedad = $seccion['variedad'];
                        $seccion_lote->tipo_variedad = $seccion['tipoVariedad'];
                        $seccion_lote->tipo_suelo = $seccion['tipoSuelo'];
                        $seccion_lote->latitud = $seccion['latitud'];
                        $seccion_lote->longitud = $seccion['longitud'];
                        $seccion_lote->estado = $seccion['activo'];
                        $seccion_lote->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $status = $seccion_lote->save();
                    endforeach;

                    if ($status) {
                        DB::commit();
                        $this->out = $this->respuesta_json('success', 200, "Datos registrados correctamente");
                    } else {
                        throw new \Exception('Error, los datos no se han registrado correctamente.');
                    }

                    return response()->json($this->out, 200);
                }
                $this->out['errors'] = $validacion->errors()->all();
                throw new \Exception('Error en la validacion de datos.');
            }

            throw new \Exception('No se han recibido parametros.');
        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function show($id)
    {
        try {

        } catch (\Exception $ex) {

        }
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
