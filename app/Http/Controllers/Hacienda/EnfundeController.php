<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Hacienda\Empleado;
use App\Models\Hacienda\Enfunde;
use App\Models\Hacienda\EnfundeDet;
use App\Models\Hacienda\Hacienda;
use App\Models\Hacienda\InventarioEmpleado;
use App\Models\Hacienda\LoteSeccionLaborEmp;
use App\Models\Hacienda\LoteSeccionLaborEmpDet;
use App\Models\Sistema\Calendario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use MongoDB\Driver\Exception\Exception;

class EnfundeController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show', 'getEmpleados', 'getLoteros',
            'getEnfundeDetalle', 'getEnfundeSemanal', 'getEnfundeSemanalDetail', 'closeEnfundeSemanal']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        $haciendas = Hacienda::select('id', 'detalle')->get();
        //Categorias chart
        $periodos = Calendario::groupBy('periodo')->select('periodo')->get()->pluck('periodo');

        //Data por hacienda 1
        $datas = array();
        $categorias = array();
        foreach ($haciendas as $hacienda):
            $hacienda_data = new \stdClass();
            $hacienda_data->name = $hacienda->detalle;
            $detalle = array();
            $values = array();
            foreach ($periodos as $periodo):
                $semanas = Calendario::selectRaw('distinct codigo')
                    ->where('periodo', $periodo)
                    ->whereRaw('DATEPART(YEAR, fecha) = 2020')
                    ->get()->pluck('codigo');
                $enfunde = Enfunde::groupBy('calendario.periodo')
                    ->rightJoin('SIS_CALENDARIO_DOLE as calendario', [
                        'calendario.codigo' => 'HAC_ENFUNDES.idcalendar',
                        'calendario.fecha' => 'HAC_ENFUNDES.fecha'
                    ])
                    ->leftJoin('HAC_DET_ENFUNDES as detalle', 'detalle.idenfunde', 'HAC_ENFUNDES.id')
                    ->select('calendario.periodo', DB::raw('ISNULL(SUM(detalle.cant_pre) + SUM(detalle.cant_fut), 0) As total'))
                    ->whereRaw('DATEPART(YEAR, HAC_ENFUNDES.fecha) = 2020')
                    ->whereIn('HAC_ENFUNDES.idcalendar', $semanas)
                    ->where('HAC_ENFUNDES.idhacienda', $hacienda->id)
                    ->get()->pluck('total');
                array_push($detalle, $enfunde);
            endforeach;

            foreach ($detalle as $value):
                array_push($values, count($value) > 0 ? intval($value[0]) : count($value));
            endforeach;
            $hacienda_data->data = $values;
            array_push($datas, $hacienda_data);
        endforeach;

        return response()->json([
            'series' => $datas,
            'categories' => $periodos
        ], 200);
        //Data por hacienda 2
    }

    public function getLoteros(Request $request)
    {
        try {
            $codigoCalendar = $request->get('calendario');

            $loteros = Empleado::where([
                'idlabor' => 3
            ])->get();

            $this->out['dataArray'] = [];

            if (count($loteros) > 0) {
                $loteros = Empleado::groupBy('HAC_EMPLEADOS.id', 'HAC_EMPLEADOS.codigo')
                    ->leftJoin('HAC_INVENTARIO_EMPLEADO as inventario', 'inventario.idempleado', 'HAC_EMPLEADOS.id')
                    ->leftJoin('BOD_MATERIALES as material', 'material.id', 'inventario.idmaterial')
                    ->where('material.descripcion', 'like', '%funda%')
                    ->select('HAC_EMPLEADOS.id', 'HAC_EMPLEADOS.codigo', DB::raw('ISNULL(SUM(inventario.tot_egreso), 0) As total'))
                    ->where([
                        'idlabor' => 3,
                        'HAC_EMPLEADOS.estado' => true
                    ])->get();

                $enfunde = Enfunde::where(['idcalendar' => $codigoCalendar])->first();

                $detalleEnfunde = array();
                if (is_object($enfunde) && !empty($enfunde)) {
                    $detalleEnfunde = EnfundeDet::where(['idenfunde' => $enfunde->id])
                        ->with(['seccion' => function ($query) {
                            $query->select('id', 'idcabecera', 'has');
                            $query->with(['cabSeccionLabor' => function ($query) {
                                $query->select('id', 'idempleado');
                            }]);
                        }])->get();
                }

                $loteros_hacienda = Empleado::select('id', 'codigo', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'nombres', 'idhacienda', 'idlabor')
                    ->where([
                        'idlabor' => 3,
                        'HAC_EMPLEADOS.estado' => true
                    ])
                    ->with(['hacienda' => function ($query) {
                        $query->select('id', 'detalle as descripcion');
                    }])->paginate(5);

                foreach ($loteros_hacienda as $lotero):
                    $lotero['total'] = 0;
                    $lotero['presente'] = false;
                    $lotero['futuro'] = false;
                    $lotero['enfunde'] = 0;

                    foreach ($loteros as $activos):
                        if ($activos->id == $lotero->id):
                            $lotero['total'] = $activos->total;
                        endif;
                    endforeach;
                    if (count($detalleEnfunde) > 0) {
                        foreach ($detalleEnfunde as $enfundeLotero):
                            if ($enfundeLotero->seccion->cabSeccionLabor->idempleado == $lotero->id) {
                                $lotero['enfunde'] += $enfundeLotero->cant_pre + $enfundeLotero->cant_fut;
                                if ($enfundeLotero->cant_pre > 0)
                                    $lotero['presente'] = true;
                                if ($enfundeLotero->cant_fut > 0)
                                    $lotero['presente'] = true;
                            }
                        endforeach;
                    }
                endforeach;

                $loteros_pend = Empleado::select('id', 'codigo', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'nombres')
                    ->where([
                        'idlabor' => 3,
                        'HAC_EMPLEADOS.estado' => true
                    ])->get();

                $loteros_pendientes = array();
                foreach ($loteros_pend as $loterop):
                    $loterop['enfunde'] = 0;
                    foreach ($detalleEnfunde as $enfundeLotero):
                        if ($enfundeLotero->seccion->cabSeccionLabor->idempleado == $loterop->id) {
                            $loterop['enfunde'] += $enfundeLotero->cant_pre + $enfundeLotero->cant_fut;
                            if ($enfundeLotero->cant_pre > 0)
                                $loterop['presente'] = true;
                            if ($enfundeLotero->cant_fut > 0)
                                $loterop['presente'] = true;
                        }
                    endforeach;
                    if ($loterop['enfunde'] == 0) {
                        $loteros_pendiente = Empleado::select('id', 'codigo', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'nombres', 'idhacienda', 'idlabor')
                            ->where([
                                'id' => $loterop->id,
                                'idlabor' => 3,
                                'HAC_EMPLEADOS.estado' => true
                            ])->get();
                        array_push($loteros_pendientes, $loteros_pendiente);
                    }
                endforeach;

                $this->out = $this->respuesta_json('success', 200, 'Loteros encontrados');
                $this->out['dataArrayPendientes'] = $loteros_pendientes;
                $this->out['dataArray'] = $loteros_hacienda;
                return response()->json($this->out, $this->out['code']);
            }

            throw new \Exception('No hay loteros registrados');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
        }

    }

    public function getEnfundeSemanal()
    {
        try {
            $enfundeSemanal = Enfunde::groupBy('calendario.periodo',
                'calendario.semana', 'calendario.color',
                'HAC_ENFUNDES.idhacienda', 'HAC_ENFUNDES.fecha', 'HAC_ENFUNDES.cerrado', 'HAC_ENFUNDES.id')
                ->orderBy('calendario.periodo', 'desc')
                ->orderBy('calendario.semana', 'desc')
                ->orderBy('HAC_ENFUNDES.idhacienda')
                ->rightJoin('SIS_CALENDARIO_DOLE as calendario', [
                    'calendario.codigo' => 'HAC_ENFUNDES.idcalendar',
                    'calendario.fecha' => 'HAC_ENFUNDES.fecha'
                ])
                ->join('HAC_DET_ENFUNDES as detalle', 'detalle.idenfunde', 'HAC_ENFUNDES.id')
                ->join('HAC_LOTSEC_LABEMPLEADO_DET as seccion', 'seccion.id', 'detalle.idseccion')
                ->select('HAC_ENFUNDES.id', DB::raw('DATEPART(YEAR,HAC_ENFUNDES.fecha) as year'), 'calendario.color',
                    'calendario.periodo', 'calendario.semana', 'HAC_ENFUNDES.idhacienda',
                    DB::raw('ISNULL(SUM(detalle.cant_pre) + SUM(detalle.cant_fut), 0) As total'),
                    DB::raw('ISNULL(SUM(detalle.cant_desb), 0) As desbunche'),
                    DB::raw('ISNULL(SUM(seccion.has), 0) As has'), 'HAC_ENFUNDES.cerrado')
                ->with('hacienda')
                ->paginate('7');
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados');
            $this->out['dataArray'] = $enfundeSemanal;
            return response()->json($this->out, 200);
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function getEnfundeSemanalDetail($id)
    {
        try {
            $enfundeSemanal = Enfunde::groupBy('calendario.periodo',
                'calendario.semana', 'calendario.color',
                'HAC_ENFUNDES.idhacienda', 'HAC_ENFUNDES.cerrado', 'HAC_ENFUNDES.id')
                ->orderBy('HAC_ENFUNDES.idhacienda')
                ->rightJoin('SIS_CALENDARIO_DOLE as calendario', [
                    'calendario.codigo' => 'HAC_ENFUNDES.idcalendar',
                    'calendario.fecha' => 'HAC_ENFUNDES.fecha'
                ])
                ->join('HAC_DET_ENFUNDES as detalle', 'detalle.idenfunde', 'HAC_ENFUNDES.id')
                ->join('HAC_LOTSEC_LABEMPLEADO_DET as seccion', 'seccion.id', 'detalle.idseccion')
                ->select('HAC_ENFUNDES.id', 'calendario.color',
                    'calendario.periodo', 'calendario.semana', 'HAC_ENFUNDES.idhacienda',
                    DB::raw('ISNULL(SUM(detalle.cant_pre) + SUM(detalle.cant_fut), 0) As total'),
                    DB::raw('ISNULL(SUM(detalle.cant_desb), 0) As desbunche'),
                    DB::raw('ISNULL(SUM(seccion.has), 0) As has'), 'HAC_ENFUNDES.cerrado')
                ->with('hacienda')
                ->where('HAC_ENFUNDES.id', $id)
                ->first();

            $enfundeSemanalDetail = EnfundeDet::groupBy('seccion.idlote_sec', 'loteSec.alias')
                ->join('HAC_LOTSEC_LABEMPLEADO_DET as seccion', 'seccion.id', 'HAC_DET_ENFUNDES.idseccion')
                ->join('HAC_LOTES_SECCION as loteSec', 'loteSec.id', 'seccion.idlote_sec')
                ->select('seccion.idlote_sec', 'loteSec.alias',
                    DB::raw('ISNULL(SUM(cant_pre), 0) As cant_pre'),
                    DB::raw('ISNULL(SUM(cant_fut), 0) As cant_fut'))
                ->where('idenfunde', $id)
                ->get();
            $total_semana = 0;

            foreach ($enfundeSemanalDetail as $total):
                $total_semana += intval($total->cant_pre) + intval($total->cant_fut);
            endforeach;

            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados');
            $this->out['dataArray'] = $enfundeSemanalDetail;
            $this->out['dataSemana'] = $enfundeSemanal;
            $this->out['totalSemana'] = $total_semana;
            return response()->json($this->out, 200);
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
        }
    }


    public function store(Request $request)
    {
        try {
            $json = $request->input('json', null);
            $params = json_decode($json);
            $params_array = json_decode($json, true);
            if (is_object($params) && !empty($params)) {
                $validacion = Validator::make($params_array, [
                    'cabecera' => 'required',
                    'detalle' => 'required'
                ]);

                if (!$validacion->fails()) {
                    $cabecera = $params_array['cabecera'];
                    $detalle = $params_array['detalle'];

                    if (!empty($cabecera['fecha'])) {
                        $fecha = strtotime(str_replace('/', '-', $cabecera['fecha']));
                        $cabecera['fecha'] = date(config('constants.date'), $fecha);

                        DB::beginTransaction();

                        $calendario = Calendario::where('fecha', $cabecera['fecha'])->first();
                        $enfunde = Enfunde::where('idcalendar', $calendario['codigo'])
                            ->where('idhacienda', $cabecera['hacienda']['id'])
                            ->first();

                        if (!$enfunde && empty($enfunde) && !is_object($enfunde)) {
                            $enfunde = new Enfunde();
                            $enfunde->idcalendar = $calendario['codigo'];
                            $enfunde->idhacienda = $cabecera['hacienda']['id'];
                            $enfunde->idlabor = $cabecera['labor']['id'];
                            $enfunde->fecha = $cabecera['fecha'];
                            $enfunde->presente = 1;
                            $enfunde->futuro = 0;
                            $enfunde->cerrado = 0;
                            $enfunde->created_at = Carbon::now()->format(config('constants.format_date'));
                            $enfunde->updated_at = Carbon::now()->format(config('constants.format_date'));
                        }
                        $enfunde->save();

                        //Verificar materiales usados en el enfunde presente y futuro
                        /* $materiales_usados = array();
                         foreach ($detalle as $item):
                             if (isset($item['presente'])) {
                                 foreach ($item['presente'] as $material):
                                     array_push($materiales_usados, $material['detalle']['material']['id']);
                                 endforeach;
                             }

                             if (isset($item['futuro'])) {
                                 foreach ($item['futuro'] as $material):
                                     array_push($materiales_usados, $material['detalle']['material']['id']);
                                 endforeach;
                             }

                         endforeach;
                         $loteros_reelevos = array();
                         foreach ($detalle as $item):
                             if (isset($item['presente'])) {
                                 foreach ($item['presente'] as $reelevo):
                                     if ($reelevo['reelevo'])
                                         array_push($loteros_reelevos, $reelevo['reelevo']['id']);
                                 endforeach;
                             }

                             if (isset($item['futuro'])) {
                                 foreach ($item['futuro'] as $reelevo):
                                     if ($reelevo['reelevo'])
                                         array_push($loteros_reelevos, $reelevo['reelevo']['id']);
                                 endforeach;
                             }
                         endforeach;

                         $materiales = array_map("unserialize", array_unique(array_map("serialize", $materiales_usados)));
                         $reelevos = array_map("unserialize", array_unique(array_map("serialize", $loteros_reelevos)));
                         //$empleados = array_merge([$cabecera['empleado']['id']], $reelevos);
                        // $this->setMaterialesUsados([$cabecera['empleado']['id']], $enfunde->idcalendar, $materiales);*/


                        foreach ($detalle as $item):
                            //return response()->json(['dato' => $item['presente'][2]], 200);
                            if (isset($item['presente'])) {
                                $this->detalleEnfunde($enfunde, $item['presente'], $cabecera['empleado']);
                            }

                            if (isset($item['futuro'])) {
                                $this->detalleEnfunde($enfunde, $item['futuro'], $cabecera['empleado'], false);
                            }
                        endforeach;

                        DB::commit();
                        $this->out = $this->respuesta_json('success', 200, 'Enfunde registrado correctamente');
                        return response()->json($this->out, 200);
                    }
                    throw new \Exception('No se han encontrado datos en el calendario');
                }

                $this->out['errors'] = $validacion->errors()->all();
                throw new  \Exception('Conflicto en la validacion de los datos');
            }
            throw new \Exception('No se han recibido parametros');
        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function setMaterialesUsados($empleados, $calendario, $materiales)
    {
        try {
            //Verificar materiales usados en el enfunde presente y futuro
            $inventarios = InventarioEmpleado::where([
                'idcalendar' => $calendario
            ])
                ->whereIn('idempleado', $empleados)
                ->whereIn('idmaterial', $materiales)->get();

            //return response()->json($materiales, 200);
            foreach ($inventarios as $inventario):
                $inventario['tot_devolucion'] = 0;
                $inventario['sld_final'] = intval($inventario['sld_inicial']) + intval($inventario['tot_egreso']);
                $inventario->save();
            endforeach;

            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function detalleEnfunde($enfunde, $detalle, $empleado, $presente = true)
    {
        try {
            $cantidad = 0;
            foreach ($detalle as $semana):
                $enfunde_detalle = EnfundeDet::where([
                    'idenfunde' => $enfunde->id,
                    'idmaterial' => $semana['detalle']['material']['id'],
                    'idseccion' => $semana['distribucion']['id'],
                ]);

                if ($semana['reelevo']) {
                    $enfunde_detalle = $enfunde_detalle->where('idreelevo', $semana['reelevo']['id']);
                }

                $enfunde_detalle = $enfunde_detalle->first();

                $cantidad = 0;

                if (!is_object($enfunde_detalle) && empty($enfunde_detalle)) {
                    $enfunde_detalle = new EnfundeDet();
                    $enfunde_detalle->idenfunde = $enfunde->id;
                    $enfunde_detalle->idmaterial = $semana['detalle']['material']['id'];
                    $enfunde_detalle->idseccion = $semana['distribucion']['id'];

                    if ($semana['reelevo']) {
                        $enfunde_detalle->reelevo = 1;
                        $enfunde_detalle->idreelevo = $semana['reelevo']['id'];
                    }

                    $enfunde_detalle->created_at = Carbon::now()->format(config('constants.format_date'));
                } else {
                    if ($presente) {
                        $cantidad = $enfunde_detalle->cant_pre;
                    } else {
                        $cantidad = $enfunde_detalle->cant_fut;
                    }
                }

                if ($semana['reelevo']) {
                    $inventario = InventarioEmpleado::where([
                        'idempleado' => $semana['reelevo']['id'],
                        'idmaterial' => $semana['detalle']['material']['id'],
                        'idcalendar' => $enfunde->idcalendar
                    ])->first();

                    $enfunde_detalle->reelevo = 1;
                    $enfunde_detalle->idreelevo = $semana['reelevo']['id'];
                    //$this->updateInventaryEmpleado($enfunde->idcalendar, $semana['detalle']['material'], $semana['reelevo'], $semana['cantidad']);
                } else {
                    $inventario = InventarioEmpleado::where([
                        'idempleado' => $empleado['id'],
                        'idmaterial' => $semana['detalle']['material']['id'],
                        'idcalendar' => $enfunde->idcalendar
                    ])->first();
                    //$this->updateInventaryEmpleado($enfunde->idcalendar, $semana['detalle']['material'], $empleado, $semana['cantidad']);
                }

                if (is_object($inventario)) {
                    if ($inventario->tot_devolucion >= $cantidad) {
                        $inventario->tot_devolucion = ($inventario->tot_devolucion - $cantidad) + $semana['cantidad'];
                    } else {
                        $inventario->tot_devolucion = $inventario->tot_devolucion + $semana['cantidad'];
                    }
                    $inventario->sld_final = ($inventario->sld_inicial + $inventario->tot_egreso) - $inventario->tot_devolucion;
                    $inventario->save();
                }

                if ($presente) {
                    $enfunde_detalle->cant_pre = $semana['cantidad'];
                } else {
                    $enfunde_detalle->cant_fut = $semana['cantidad'];
                    $enfunde_detalle->cant_desb = $semana['desbunche'];
                }

                $enfunde_detalle->updated_at = Carbon::now()->format(config('constants.format_date'));
                $enfunde_detalle->save();
            endforeach;
            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function updateInventaryEmpleado($calendario, $material, $empleado, $cantidad)
    {
        try {
            $inventario = InventarioEmpleado::where([
                'idempleado' => $empleado['id'],
                'idmaterial' => $material['id'],
                'idcalendar' => $calendario
            ])->first();

            if (is_object($inventario) && !empty($inventario)) {
                //Bajar inventario
                $inventario->tot_devolucion += intval($cantidad);
                $inventario->sld_final = (+$inventario->sld_inicial + $inventario->tot_egreso) - $inventario->tot_devolucion;
                $inventario->updated_at = Carbon::now()->format(config('constants.format_date'));
                $inventario->save();
            }
            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function closeEnfundeSemanal($id)
    {
        try {
            $enfunde = Enfunde::where('id', $id)->first();
            $enfunde_detalle = EnfundeDet::where('idenfunde', $enfunde->id)
                ->with(['seccion' => function ($query) {
                    $query->with('cabSeccionLabor');
                }])
                ->get();

            if (is_object($enfunde) && count($enfunde_detalle) > 0) {
                DB::beginTransaction();
                $futuro = strtotime(str_replace('/', '-', $enfunde->fecha . "+ 7 days"));
                $fecha_fut = date(config('constants.date'), $futuro);
                $calendario_fut = Calendario::where('fecha', $fecha_fut)->first();

                foreach ($enfunde_detalle as $detalle):
                    $idempleado = $detalle->seccion->cabSeccionLabor->idempleado;

                    if ($detalle->reelevo) {
                        $idempleado = $detalle->idreelevo;
                    }

                    $inventario = InventarioEmpleado::where([
                        'idempleado' => $idempleado,
                        'idmaterial' => $detalle->idmaterial,
                        'idcalendar' => $enfunde->idcalendar
                    ])->first();

                    if (is_object($inventario)) {
                        $inventario->estado = false;
                        $inventario->save();

                        $saldo_final = $inventario->sld_final;

                        $inventario_empleado = InventarioEmpleado::where([
                            'idempleado' => $idempleado,
                            'idmaterial' => $detalle->idmaterial,
                            'idcalendar' => $calendario_fut->codigo
                        ])->first();

                        if (!is_object($inventario_empleado)) {
                            $inventario_empleado = new InventarioEmpleado();
                            $inventario_empleado->codigo = $this->codigoTransaccionInventario($enfunde->idhacienda);
                            $inventario_empleado->idempleado = $idempleado;
                            $inventario_empleado->idmaterial = $detalle->idmaterial;
                            $inventario_empleado->idcalendar = $calendario_fut->codigo;
                            $inventario_empleado->tot_egreso = 0;
                            $inventario_empleado->tot_devolucion = 0;
                            $inventario_empleado->created_at = Carbon::now()->format(config('constants.format_date'));
                        }

                        $inventario_empleado->sld_inicial = $saldo_final;
                        $inventario_empleado->sld_final = (+$inventario_empleado->sld_inicial + +$inventario_empleado->tot_egreso) - $inventario_empleado->tot_devolucion;
                        $inventario_empleado->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $inventario_empleado->save();
                    }
                endforeach;

                $enfunde->futuro = true;
                $enfunde->cerrado = true;
                $enfunde->save();

                DB::commit();
                $this->out = $this->respuesta_json('success', 200, 'Enfunde cerrado con exito');
                return response()->json($this->out, 200);
            }

            throw new \Exception('No se puede cerrar el enfunde');

        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function codigoTransaccionInventario($hacienda = 1)
    {
        $transacciones = InventarioEmpleado::select('codigo')->get();
        $path = $hacienda == 1 ? 'PRI-INV' : 'SFC-INV';
        $codigo = $path . '-' . str_pad(count($transacciones) + 1, 6, "0", STR_PAD_LEFT);;
        return $codigo;
    }

    public function getEnfundeDetalle(Request $request)
    {
        try {
            $idhacienda = $request->get('hacienda');
            $idcalendar = $request->get('calendario');
            $idseccion = $request->get('seccion');
            $idempleado = $request->get('empleado');
            $grupo = $request->get('grupoMaterial');

            if (!empty($idcalendar)) {
                $secciones_empleado = LoteSeccionLaborEmp::where('idempleado', $idempleado)->first();
                $secciones = LoteSeccionLaborEmpDet::select('id')
                    ->where('idcabecera', $secciones_empleado->id)
                    ->get()->pluck('id');

                $enfundeDetalle = Enfunde::select('id', 'idcalendar', 'fecha', 'presente', 'futuro', 'cerrado', 'estado')
                    ->where([
                        'idcalendar' => $idcalendar,
                        'idhacienda' => $idhacienda
                    ])->with(['detalle' => function ($query) use ($secciones, $idseccion) {
                        $query->select('id', 'idenfunde', 'idmaterial', 'idseccion', 'cant_pre', 'cant_fut', 'cant_desb', 'reelevo', 'idreelevo');
                        $query->whereIn('idseccion', $secciones);
                        $query->with(['seccion' => function ($query) {
                            $query->select('id', 'idlote_sec');
                            $query->with(['seccionLote' => function ($query) {
                                $query->selectRaw("id, idlote, (alias + ' - has: ' + CONVERT(varchar, has)) as descripcion, alias, has, estado");
                                $query->with(['lote' => function ($query) {
                                    $query->select('id', 'identificacion', 'idhacienda', 'has', 'estado');
                                }]);
                            }]);
                        }]);
                    }])->first();

                $datos = array();
                if (is_object($enfundeDetalle)) {
                    foreach ($enfundeDetalle->detalle as $detalle):
                        $data = new \stdClass();
                        $data->presente = array();
                        $data->futuro = array();
                        $data->totalP = 0;
                        $data->totalF = 0;
                        $data->totalD = 0;
                        $data->idseccion = $detalle->seccion['id'];
                        $reelevo = null;

                        if ($detalle->reelevo) {
                            $idempleado = $detalle->idreelevo;
                            $reelevo = Empleado::select('id', 'idhacienda', 'cedula', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'nombres')
                                ->where(['id' => $detalle->idreelevo])->first();
                        }

                        $inventario = InventarioEmpleado::select('id', 'idempleado', 'idmaterial', 'sld_inicial', 'tot_egreso', 'tot_devolucion', 'sld_final', 'estado')
                            ->where([
                                'idempleado' => $idempleado,
                                'idmaterial' => $detalle->idmaterial,
                                'idcalendar' => $idcalendar,
                            ])->with(['material' => function ($query) {
                                $query->select('id', 'descripcion', 'stock');
                            }])
                            ->first();

                        $enfunde = new \stdClass();
                        $enfunde->id = $detalle->id;
                        $enfunde->fecha = $enfundeDetalle->fecha;
                        $enfunde->distribucion = new \stdClass();
                        $enfunde->distribucion->id = $detalle->seccion['id'];
                        $enfunde->distribucion->loteSeccion = $detalle->seccion['seccionLote'];
                        $enfunde->detalle = $inventario;
                        $enfunde->reelevo = $reelevo;
                        $enfunde->contabilizar = false;
                        //Para la edicion de un item que no se contabiliza
                        $enfunde->edicion = 0;
                        if (intval($detalle['cant_pre']) > 0) {
                            $enfundePresente = clone $enfunde;
                            $enfundePresente->cantidad = intval($detalle['cant_pre']);
                            $data->totalP += $enfundePresente->cantidad;
                            $enfundePresente->desbunche = 0;
                            array_push($data->presente, $enfundePresente);
                        }

                        if (intval($detalle['cant_fut']) > 0) {
                            $enfundeFuturo = clone $enfunde;
                            $enfundeFuturo->cantidad = intval($detalle['cant_fut']);
                            $enfundeFuturo->desbunche = intval($detalle['cant_desb']);
                            $data->totalF += $enfundeFuturo->cantidad;
                            $data->totalD += $enfundeFuturo->desbunche;
                            array_push($data->futuro, $enfundeFuturo);
                        }
                        array_push($datos, $data);
                    endforeach;
                }

                $this->out = $this->respuesta_json('success', 200, 'Se devuelven registros');
                $this->out['dataArray'] = $datos;
                return response()->json($this->out, 200);
            }
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
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
        try {


            throw new \Exception('No se encontraron datos');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function deleteEnfunde(Request $request)
    {
        try {
            $json = $request->input('json');
            $params = json_decode($json);
            $params_array = json_decode($json, true);
            if (is_object($params) && !empty($params)) {
                $validacion = Validator::make($params_array, [
                    'eliminar' => 'required',
                ]);

                if (!$validacion->fails()) {
                    $respuesta = false;
                    DB::beginTransaction();
                    foreach ($params->eliminar as $item):
                        $enfunde = Enfunde::where([
                            'idcalendar' => $item->calendario,
                            'idhacienda' => $item->hacienda
                        ])->first();

                        $enfunde_detalle = EnfundeDet::where([
                            'idenfunde' => $enfunde->id,
                            'idmaterial' => $item->material,
                            'idseccion' => $item->seccion
                        ]);

                        if (is_object($item->reelevo)) {
                            $enfunde_detalle = $enfunde_detalle->where(['idreelevo' => $item->reelevo->id]);
                        }

                        $enfunde_detalle = $enfunde_detalle->first();

                        if (is_object($enfunde_detalle)) {
                            $seccion_empleado = LoteSeccionLaborEmpDet::where(['id' => $item->seccion])
                                ->with(['cabSeccionLabor' => function ($query) {
                                    $query->select('id', 'idempleado');
                                }])
                                ->first();

                            if (is_object($seccion_empleado)) {
                                //Inventario
                                $empleado = $seccion_empleado->cabSeccionLabor->idempleado;

                                if ($enfunde_detalle->reelevo) {
                                    $empleado = $enfunde_detalle->idreelevo;
                                }

                                $inventario = InventarioEmpleado::where([
                                    'idcalendar' => $item->calendario,
                                    'idempleado' => $empleado,
                                    'idmaterial' => $item->material
                                ])->first();

                                $inventario->tot_devolucion = $inventario->tot_devolucion - $item->cantidad;
                                $inventario->sld_final = ($inventario->sld_inicial + $inventario->tot_egreso) - $inventario->tot_devolucion;
                                $respuesta = $inventario->save();
                                //return response()->json($inventario, 200);

                                $item->futuro = filter_var($item->futuro, FILTER_VALIDATE_BOOLEAN);
                                $item->presente = filter_var($item->presente, FILTER_VALIDATE_BOOLEAN);

                                if ($respuesta) {
                                    if ($item->futuro) {
                                        $enfunde_detalle->cant_fut = $enfunde_detalle->cant_fut - $item->cantidad;
                                    }

                                    if ($item->presente) {
                                        $enfunde_detalle->cant_pre = $enfunde_detalle->cant_pre - $item->cantidad;
                                    }

                                    $respuesta = $enfunde_detalle->save();

                                    if ($enfunde_detalle->cant_fut == 0 && $enfunde_detalle->cant_pre == 0) {
                                        $respuesta = $enfunde_detalle->delete();
                                    }
                                }
                            } else {
                                $respuesta = false;
                            }
                        } else {
                            $respuesta = false;
                        }
                    endforeach;
                    if ($respuesta) {
                        DB::commit();
                        $this->out = $this->respuesta_json('success', 200, 'Registros eliminados con exito');
                        return response()->json($this->out, $this->out['code']);
                    } else {
                        throw new \Exception('No se pudo procesar esta transaccion');
                    }
                }

                $this->out['errors'] = $validacion->errors()->all();
                throw new \Exception('No se han recibido los datos completos');
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['message'] = $ex->getMessage();
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
