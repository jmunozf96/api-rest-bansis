<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Hacienda\LoteSeccion;
use App\Models\Hacienda\LoteSeccionLaborEmp;
use App\Models\Hacienda\LoteSeccionLaborEmpDet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LoteSeccionLaborEmpController extends Controller
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

            if (!empty($params) && is_object($params) && count($params_array) > 0) {
                $validacion = Validator::make($params_array, [
                    'cabeceraDistribucion' => 'required',
                    'cabeceraDistribucion.hacienda' => 'required',
                    'cabeceraDistribucion.labor' => 'required',
                    'cabeceraDistribucion.empleado' => 'required',
                    'detalleDistribucion' => 'required|Array'
                ]);

                if (!$validacion->fails()) {
                    DB::beginTransaction();
                    $cabecera = $params_array['cabeceraDistribucion'];
                    $detalle = $params_array['detalleDistribucion'];

                    //Preguntar si no existe la cabecera
                    $laborSeccionEmpleado = LoteSeccionLaborEmp::where([
                        'idlabor' => $cabecera['labor']['id'],
                        'idempleado' => $cabecera['empleado']['id']
                    ])->first();

                    if (!is_object($laborSeccionEmpleado) && empty($laborSeccionEmpleado)) {
                        $laborSeccionEmpleado = new LoteSeccionLaborEmp();
                        $laborSeccionEmpleado->idlabor = $cabecera['labor']['id'];
                        $laborSeccionEmpleado->idempleado = $cabecera['empleado']['id'];
                        $laborSeccionEmpleado->has = $cabecera['hasTotal'];
                        $laborSeccionEmpleado->created_at = Carbon::now()->format(config('constants.format_date'));
                        $laborSeccionEmpleado->updated_at = Carbon::now()->format(config('constants.format_date'));
                    } else {
                        $laborSeccionEmpleado->has = $cabecera['hasTotal'];
                        $laborSeccionEmpleado->updated_at = Carbon::now()->format(config('constants.format_date'));
                    }

                    $status = $laborSeccionEmpleado->save();

                    if ($status) {
                        //Guardar detalle
                        foreach ($detalle as $item):
                            $timestamp = strtotime(str_replace('/', '-', $item['fecha']));
                            $item['fecha'] = date(config('constants.date'), $timestamp);
                            $laborSeccionEmpleadoDetalle = LoteSeccionLaborEmpDet::where([
                                'idcabecera' => $laborSeccionEmpleado->id,
                                'idlote_sec' => $item['loteSeccion']['id']
                            ])->first();
                            if (!is_object($laborSeccionEmpleadoDetalle) && empty($laborSeccionEmpleadoDetalle)) {
                                $laborSeccionEmpleadoDetalle = new LoteSeccionLaborEmpDet();
                                $laborSeccionEmpleadoDetalle->fecha_apertura = $item['fecha'];
                                $laborSeccionEmpleadoDetalle->idcabecera = $laborSeccionEmpleado->id;
                                $laborSeccionEmpleadoDetalle->idlote_sec = $item['loteSeccion']['id'];
                                $laborSeccionEmpleadoDetalle->has = $item['hasDistribucion'];
                                $laborSeccionEmpleadoDetalle->created_at = Carbon::now()->format(config('constants.format_date'));
                                $laborSeccionEmpleadoDetalle->updated_at = Carbon::now()->format(config('constants.format_date'));
                            } else {
                                $laborSeccionEmpleadoDetalle->has = $item['hasDistribucion'];
                                $laborSeccionEmpleadoDetalle->updated_at = Carbon::now()->format(config('constants.format_date'));
                            }

                            $status = $laborSeccionEmpleadoDetalle->save();

                            if (!$status) {
                                throw new \Exception('No se pudo completar la transaccion');
                            }

                        endforeach;
                        DB::commit();

                        return response()->json($detalle, 200);
                    }

                }
                $this->out['code'] = 500;
                $this->out['errors'] = $validacion->errors()->all();
                throw new \Exception('Se encontraron errores en la validacion');
            }
            throw new \Exception('No se han recibido paramestros');
        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function getHasSeccionDisponibles(Request $request)
    {
        $total = 0;
        $idseccion = $request->get('seccion');
        $idcabecera = $request->get('cabecera');
        //$seccion = LoteSeccion::where('id', $idseccion)->first();
        $detalles = LoteSeccionLaborEmpDet::where(['idlote_sec' => $idseccion])->get();
        if (count($detalles) > 0) {
            foreach ($detalles as $detalle):
                if ($detalle['idcabecera'] != $idcabecera) {
                    $total += floatval($detalle['has']);
                }
            endforeach;
        }
        return response()->json([
            'idlote' => $idseccion,
            'hasDistribuidas' => round($total, 2)
        ], 200);
    }

    public function getLaboresSeccionEmpleado(Request $request)
    {
        try {
            $labor = $request->get('labor');
            $empleado = $request->get('empleado');

            if (!empty($labor) && !empty($empleado)) {
                $secciones = LoteSeccionLaborEmp::where([
                    'idlabor' => $labor,
                    'idempleado' => $empleado
                ])
                    ->with(['detalleSeccionLabor' => function ($query) {
                        $query->with(['seccionLote' => function ($query) {
                            $query->selectRaw("id, idlote, (alias + ' - has: ' + CONVERT(varchar, has)) as descripcion, alias, has, estado");
                            $query->with(['lote' => function ($query) {
                                $query->select('id', 'identificacion', 'idhacienda', 'has', 'estado');
                            }]);
                        }]);
                        $query->select('id as idDetalle', 'fecha_apertura as fecha', 'idcabecera', 'idlote_sec', 'has');
                    }])
                    ->select('id', 'idlabor', 'idempleado', 'has')
                    ->first();

                return response(['secciones' => $secciones], 200);
            }

            throw new \Exception('No se han recibido parametros');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response($this->out, 404);
        }
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
