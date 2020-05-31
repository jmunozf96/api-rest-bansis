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

    public function index(Request $request)
    {
        $hacienda = $request->get('hacienda');
        $seccionlaboresEmpleado = LoteSeccionLaborEmp::selectRaw("id, idlabor, idempleado, has, updated_at, estado")
            ->whereHas('empleado', function ($query) use ($hacienda) {
                $query->select('id', 'idhacienda');
                if (!empty($hacienda) && isset($hacienda))
                    $query->where('idhacienda', $hacienda);
            });

        if (!is_null($seccionlaboresEmpleado)) {
            $seccionlaboresEmpleado = $seccionlaboresEmpleado->with(['labor' => function ($query) {
                $query->select('id', 'descripcion', 'estado');
            }])
                ->with(['empleado' => function ($query) use ($hacienda) {
                    $query->select('id', 'idhacienda', 'nombres', 'cedula', 'codigo', 'estado');
                    $query->with(['hacienda' => function ($query) {
                        $query->select('id', 'detalle');
                    }]);
                    if (!empty($hacienda) && isset($hacienda))
                        $query->where('idhacienda', $hacienda);
                }])
                ->with(['detalleSeccionLabor' => function ($query) {
                    $query->select('id', 'idcabecera', 'idlote_sec', 'has', 'updated_at', 'estado');
                    $query->with(['seccionLote' => function ($query) {
                        $query->select('id', 'idlote', 'alias', 'has');
                    }]);
                }]);
        }


        $seccionlaboresEmpleado = $seccionlaboresEmpleado->orderBy('updated_at', 'DESC')
            ->paginate(7);

        if (!is_null($seccionlaboresEmpleado) && !empty($seccionlaboresEmpleado) && count($seccionlaboresEmpleado) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['dataArray'] = $seccionlaboresEmpleado;
        } else {
            $this->out['message'] = 'No hay datos registrados';
        }

        return response()->json($this->out, $this->out['code']);
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
                        $laborSeccionEmpleado->estado = true;
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
                        $this->out = $this->respuesta_json('success', 200, 'Datos registrados correctamente');
                        return response()->json($this->out, 200);
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
        $idlabor = $request->get('labor');
        //$seccion = LoteSeccion::where('id', $idseccion)->first();
        $cabecera = LoteSeccionLaborEmp::where(['id' => $idcabecera, 'idlabor' => $idlabor])->first();
        $detalles = LoteSeccionLaborEmpDet::where(['idlote_sec' => $idseccion])->get();
        if (is_object($cabecera) && !empty($cabecera))
            if (count($detalles) > 0)
                foreach ($detalles as $detalle):
                    if ($detalle['idcabecera'] != $cabecera->id)
                        $total += floatval($detalle['has']);
                endforeach;

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
        try {
            $cabecera = LoteSeccionLaborEmp::where('id', $id)
                ->with(['labor' => function ($query) {
                    $query->select('id', 'descripcion');
                }])
                ->with(['empleado' => function ($query) {
                    $query->select('id', 'idhacienda', 'nombres as descripcion', 'idlabor', 'estado');
                    $query->with(['hacienda' => function ($query) {
                        $query->select('id', 'detalle as descripcion');
                    }]);
                }])
                ->first();
            if (!is_null($cabecera) && !empty($cabecera)) {
                $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
                $this->out['laborSeccion'] = $cabecera;
                return response()->json($this->out, $this->out['code']);
            } else {
                throw new \Exception('No existen datos con el parametro enviado.');
            }

        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            $detalle = LoteSeccionLaborEmp::where('id', $id)->first();
            if (is_object($detalle) && !empty($detalle)) {
                LoteSeccionLaborEmpDet::where(['idcabecera' => $id])->delete();
                LoteSeccionLaborEmp::destroy($id);
                DB::commit();
                $this->out = $this->respuesta_json('success', 200, 'Dato eliminado correctamente.');
                return response()->json($this->out, $this->out['code']);
            } else {
                throw new \Exception('No existen datos con el parametro enviado.');
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['code'] = 500;
            $this->out['message'] = $ex->getMessage();
            $this->out['message'] = 'No se puede eliminar el registro, conflicto en la base de datos, por favor contactar con el administrador del sistema.';
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function destroyDetalle($id)
    {
        try {
            DB::beginTransaction();
            $detalle = LoteSeccionLaborEmpDet::where('id', $id)->first();
            if (is_object($detalle) && !empty($detalle)) {
                $cabecera = $detalle->idcabecera;
                LoteSeccionLaborEmpDet::destroy($id);
                $detalles_restantes = LoteSeccionLaborEmpDet::where('idcabecera', $cabecera)->get();
                if (count($detalles_restantes) == 0) {
                    LoteSeccionLaborEmp::where('id', $cabecera)->update(['estado' => false]);
                }
                DB::commit();
                $this->out = $this->respuesta_json('success', 200, 'Dato eliminado correctamente.');
                return response()->json($this->out, $this->out['code']);
            } else {
                throw new \Exception('No existen datos con el parametro enviado.');
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['code'] = 500;
            $this->out['message'] = $ex->getMessage();
            $this->out['message'] = 'No se puede eliminar el registro, conflicto en la base de datos, por favor contactar con el administrador del sistema.';
            return response()->json($this->out, $this->out['code']);
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
