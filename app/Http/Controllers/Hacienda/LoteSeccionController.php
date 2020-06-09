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

    public function customSelect(Request $request)
    {
        $hacienda = $request->get('hacienda');

        if (!empty($hacienda)) {
            $lote = LoteSeccion::join('HAC_LOTES as lote', 'lote.id', 'HAC_LOTES_SECCION.idlote')
                ->selectRaw("HAC_LOTES_SECCION.id, idlote, (right('0' + lote.identificacion,2) + HAC_LOTES_SECCION.descripcion + ' - has: ' + CONVERT(varchar, HAC_LOTES_SECCION.has)) as descripcion, HAC_LOTES_SECCION.alias, HAC_LOTES_SECCION.has, HAC_LOTES_SECCION.estado")
                ->whereHas('lote', function ($query) use ($hacienda) {
                    $query->select('id', 'idhacienda');
                    $query->where(['idhacienda' => $hacienda]);
                });

            if (!is_null($lote)) {
                $lote = $lote->with(['lote' => function ($query) use ($hacienda) {
                    $query->select('id', 'identificacion', 'idhacienda', 'has', 'estado');
                    $query->where(['idhacienda' => $hacienda]);
                }])
                    ->orderByRaw("(right('0' + lote.identificacion,2) + HAC_LOTES_SECCION.descripcion + ' - has: ' + CONVERT(varchar, HAC_LOTES_SECCION.has))")
                    ->get();
            }

            if (!is_null($lote) && !empty($lote)) {
                $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
                $this->out['dataArray'] = $lote;
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
        $delete = false;
        try {
            $seccion = LoteSeccion::find($id);
            if (is_object($seccion) && !empty($seccion)) {
                LoteSeccion::destroy($id);
                $this->out = $this->respuesta_json('success', 200, 'Seccion eliminada correctamente.');
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
