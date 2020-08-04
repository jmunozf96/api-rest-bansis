<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Bodega\EgresoBodega;
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
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\PDF;

class EnfundeController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => [
            'index', 'show', 'getEmpleados', 'getLoteros',
            'getEnfundeDetalle', 'getEnfundeSemanal',
            'getEnfundeSeccion', 'getEnfundeSemanalDetail',
            'closeEnfundeSemanal', 'informeSemanalEnfunde',
            'informeSemanalEnfundeMaterial', 'informeSemanalEnfundeEmpleados', 'informeSmanalEnfundeEmpleadoMaterial',
            'enfundeSemanal_PDF'
        ]]);
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
            $hacienda = $request->get('hacienda');
            $empleado = $request->get('empleado');

            $loteros = Empleado::where([
                'idlabor' => 3
            ]);

            if (!empty($empleado) && isset($empleado) && !is_null($empleado)) {
                $loteros = $loteros->where([
                    'id' => $empleado,
                    'idhacienda' => $hacienda
                ]);
            }

            $loteros = $loteros->get();

            $this->out['dataArray'] = [];

            if (count($loteros) > 0) {
                $loteros = Empleado::groupBy('HAC_EMPLEADOS.id', 'HAC_EMPLEADOS.codigo')
                    ->leftJoin('HAC_INVENTARIO_EMPLEADO as inventario', 'inventario.idempleado', 'HAC_EMPLEADOS.id')
                    ->leftJoin('BOD_MATERIALES as material', 'material.id', 'inventario.idmaterial')
                    ->where('material.descripcion', 'like', '%funda%')
                    ->where('inventario.idcalendar', $codigoCalendar)
                    ->select('HAC_EMPLEADOS.id', 'HAC_EMPLEADOS.codigo', DB::raw('ISNULL(SUM(inventario.tot_egreso), 0) As total'))
                    ->where([
                        'idlabor' => 3,
                        'idhacienda' => $hacienda,
                        'HAC_EMPLEADOS.estado' => true
                    ]);

                if (!empty($empleado) && isset($empleado) && !is_null($empleado)) {
                    $loteros = $loteros->where('HAC_EMPLEADOS.id', $empleado);
                }

                $loteros = $loteros->get();

                $enfunde = Enfunde::where(['idcalendar' => $codigoCalendar, 'idhacienda' => $hacienda])->first();

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
                        'HAC_EMPLEADOS.estado' => true,
                        'idhacienda' => $hacienda
                    ])
                    ->with(['hacienda' => function ($query) {
                        $query->select('id', 'detalle as descripcion');
                    }]);

                if (!empty($empleado) && isset($empleado) && !is_null($empleado)) {
                    $loteros_hacienda = $loteros_hacienda->where('HAC_EMPLEADOS.id', $empleado);
                }

                $loteros_hacienda = $loteros_hacienda->paginate(5);

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
                                    $lotero['futuro'] = true;
                            }
                        endforeach;
                    }
                endforeach;

                $loteros_pend = Empleado::select('id', 'codigo', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'nombres')
                    ->where([
                        'idlabor' => 3,
                        'HAC_EMPLEADOS.estado' => true,
                        'idhacienda' => $hacienda
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

    public function getEnfundeSemanal(Request $request)
    {
        try {
            $hacienda = $request->get('hacienda');

            $enfundeSemanal = Enfunde::groupBy('calendario.periodo',
                'calendario.semana', 'calendario.color', 'HAC_ENFUNDES.idhacienda',
                'HAC_ENFUNDES.presente', 'HAC_ENFUNDES.futuro', 'HAC_ENFUNDES.cerrado',
                'HAC_ENFUNDES.fecha', 'HAC_ENFUNDES.cerrado', 'HAC_ENFUNDES.id')
                ->orderBy('calendario.periodo', 'desc')
                ->orderBy('calendario.semana', 'desc')
                ->orderBy('HAC_ENFUNDES.idhacienda')
                ->rightJoin('SIS_CALENDARIO_DOLE as calendario', 'calendario.fecha', 'HAC_ENFUNDES.fecha')
                ->join('HAC_DET_ENFUNDES as detalle', 'detalle.idenfunde', 'HAC_ENFUNDES.id')
                ->join('HAC_LOTSEC_LABEMPLEADO_DET as seccion', 'seccion.id', 'detalle.idseccion')
                ->select('HAC_ENFUNDES.id', DB::raw('DATEPART(YEAR,HAC_ENFUNDES.fecha) as year'), 'calendario.color',
                    'calendario.periodo', 'calendario.semana', 'HAC_ENFUNDES.idhacienda',
                    'HAC_ENFUNDES.presente as stPresente', 'HAC_ENFUNDES.futuro as stFuturo', 'HAC_ENFUNDES.cerrado',
                    DB::raw('ISNULL(SUM(detalle.cant_pre), 0) As presente'),
                    DB::raw('ISNULL(SUM(detalle.cant_fut), 0) As futuro'),
                    DB::raw('ISNULL(SUM(detalle.cant_pre) + SUM(detalle.cant_fut), 0) As total'),
                    DB::raw('ISNULL(SUM(detalle.cant_desb), 0) As desbunche'))
                ->with('hacienda');

            if (!empty($hacienda)) {
                $enfundeSemanal = $enfundeSemanal->where(['HAC_ENFUNDES.idhacienda' => $hacienda]);
            }

            $enfundeSemanal = $enfundeSemanal->paginate(7);

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
                'calendario.semana', 'calendario.codigo', 'calendario.color',
                'HAC_ENFUNDES.idhacienda', 'HAC_ENFUNDES.cerrado', 'HAC_ENFUNDES.id')
                ->orderBy('HAC_ENFUNDES.idhacienda')
                ->rightJoin('SIS_CALENDARIO_DOLE as calendario', [
                    'calendario.codigo' => 'HAC_ENFUNDES.idcalendar',
                    'calendario.fecha' => 'HAC_ENFUNDES.fecha'
                ])
                ->join('HAC_DET_ENFUNDES as detalle', 'detalle.idenfunde', 'HAC_ENFUNDES.id')
                ->join('HAC_LOTSEC_LABEMPLEADO_DET as seccion', 'seccion.id', 'detalle.idseccion')
                ->select('HAC_ENFUNDES.id', 'calendario.color',
                    'calendario.periodo', 'calendario.semana', 'calendario.codigo', 'HAC_ENFUNDES.idhacienda',
                    DB::raw('ISNULL(SUM(detalle.cant_pre) + SUM(detalle.cant_fut), 0) As total'),
                    DB::raw('ISNULL(SUM(detalle.cant_desb), 0) As desbunche'), 'HAC_ENFUNDES.cerrado')
                ->with('hacienda')
                ->where('HAC_ENFUNDES.id', $id)
                ->first();

            $enfundeSemanalDetail = EnfundeDet::groupBy('seccion.idlote_sec', 'loteSec.alias')
                ->join('HAC_LOTSEC_LABEMPLEADO_DET as seccion', 'seccion.id', 'HAC_DET_ENFUNDES.idseccion')
                ->join('HAC_LOTES_SECCION as loteSec', 'loteSec.id', 'seccion.idlote_sec')
                ->select('seccion.idlote_sec', 'loteSec.alias',
                    DB::raw("(right('0' + loteSec.alias,3)) as 'alias'"),
                    DB::raw('ISNULL(SUM(cant_pre), 0) As cant_pre'),
                    DB::raw('ISNULL(SUM(cant_fut), 0) As cant_fut'))
                //->orderByRaw("(SUM(cant_pre) + SUM(cant_fut)) desc")
                ->orderByRaw("(right('0' + loteSec.alias,3))")
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

    public function getEnfundeSeccion(Request $request)
    {
        try {
            $seccion = $request->get('seccion');
            $enfunde = $request->get('idenfunde');
            $calendario = $request->get('calendario');

            if (!empty($seccion) && !empty($calendario) && isset($seccion) && isset($calendario)
                && !is_null($calendario) && !is_null($seccion) && !empty($enfunde) && !is_null($enfunde)) {
                $enfunde = Enfunde::where(['idcalendar' => $calendario, 'id' => $enfunde])->first();
                if (is_object($enfunde)) {
                    $seccionLote = LoteSeccionLaborEmpDet::select('id', 'idcabecera', 'idlote_sec', 'has')
                        ->where(['idlote_sec' => $seccion])
                        ->with(['seccionLote' => function ($query) use ($seccion) {
                            $query->select('id', 'idlote', 'alias', 'has', 'fecha_siembra', 'variedad', 'tipo_variedad', 'tipo_suelo');
                            $query->where('id', $seccion);
                        }])
                        ->first();
                    $detalle_enfunde = EnfundeDet::groupBy('idenfunde', 'idseccion', 'reelevo', 'idreelevo')
                        ->select('idenfunde', 'idseccion', 'reelevo', 'idreelevo',
                            DB::raw('sum(cant_pre) as cant_pre'),
                            DB::raw('sum(cant_fut) as cant_fut')
                        )
                        ->where('idenfunde', $enfunde->id)
                        ->with(['seccion' => function ($query) use ($seccion) {
                            $query->select('id', 'idcabecera', 'idlote_sec', 'has');
                            $query->with(['seccionLote' => function ($query) use ($seccion) {
                                $query->select('id', 'idlote', 'alias', 'has');
                                $query->where('id', $seccion);
                            }]);
                            $query->with(['cabSeccionLabor' => function ($query) {
                                $query->select('id', 'idempleado', 'has');
                                $query->with(['empleado' => function ($query) {
                                    $query->select('id', 'codigo', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'nombres');
                                }]);
                            }]);
                        }])
                        ->whereHas('seccion', function ($query) use ($seccion) {
                            $query->whereHas('seccionLote', function ($query) use ($seccion) {
                                $query->where('id', $seccion);
                            });
                        })
                        ->with(['reelevo' => function ($query) {
                            $query->select('id', 'nombres', 'nombre1', 'nombre2', 'apellido1', 'apellido2');
                        }])
                        ->get();

                    if (is_object($seccionLote)) {
                        if (count($detalle_enfunde) > 0) {
                            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados con exito');
                            $this->out['dataArray'] = [
                                'seccion' => $seccionLote,
                                'detalleSemana' => $detalle_enfunde
                            ];
                            return response()->json($this->out, 200);
                        }
                    }

                }
                throw new \Exception('No se han encontrado datos de enfunde para esta semana');
            }
            throw new \Exception('No se han recibido parametros');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
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
                            $enfunde->presente = 0;
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
                            if (isset($item['presente']) && count($item['presente']) > 0) {
                                $this->detalleEnfunde($enfunde, $item['presente'], $cabecera['empleado']);
                            }

                            if (isset($item['futuro']) && count($item['futuro']) > 0) {
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
                    'idreelevo' => !is_null($semana['reelevo']) ? $semana['reelevo']['id'] : null
                ])->first();

                $cantidad = 0;

                if (!is_object($enfunde_detalle) && empty($enfunde_detalle)) {
                    $enfunde_detalle = new EnfundeDet();
                    $enfunde_detalle->idenfunde = $enfunde->id;
                    $enfunde_detalle->idmaterial = $semana['detalle']['material']['id'];
                    $enfunde_detalle->idseccion = $semana['distribucion']['id'];

                    if (!is_null($semana['reelevo']) && isset($semana['reelevo']['id'])) {
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

                if (!is_null($semana['reelevo']) && isset($semana['reelevo']['id'])) {
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
                        $inventario->tot_devolucion += $semana['cantidad'];
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
            $enfunde_detalle = EnfundeDet::where('idenfunde', $enfunde->id)->get();

            if (is_object($enfunde) && count($enfunde_detalle) > 0) {
                DB::beginTransaction();
                if ($enfunde->presente == 0) {
                    //Primer cierre Presente
                    $enfunde->presente = 1;
                    $this->out = $this->respuesta_json('success', 200, 'Enfunde Presente cerrado con exito');
                    $this->out['enfundePresente'] = [
                        "presente" => "Se ha reportado el enfunde Presente Correctamente"
                    ];
                } else if ($enfunde->presente == 1 && $enfunde->cerrado == 1) {
                    $empleados = EnfundeDet::groupBy('empleado.id')
                        ->join('HAC_LOTSEC_LABEMPLEADO_DET as sec_det', 'sec_det.id', 'HAC_DET_ENFUNDES.idseccion')
                        ->join('HAC_LOTSEC_LABEMPLEADO as seccion', 'seccion.id', 'sec_det.idcabecera')
                        ->join('HAC_EMPLEADOS as empleado', 'empleado.id', 'seccion.idempleado')
                        ->select('empleado.id')
                        ->where([
                            'idenfunde' => $enfunde->id,
                            'idreelevo' => null
                        ])->get()->pluck('id')->toArray();

                    $reelevos = EnfundeDet::groupBy('empleado.id')
                        ->join('HAC_EMPLEADOS as empleado', 'empleado.id', 'HAC_DET_ENFUNDES.idreelevo')
                        ->select('empleado.id')
                        ->where([
                            'idenfunde' => $enfunde->id,
                            'reelevo' => 1
                        ])->get()->pluck('id')->toArray();

                    //1 es el Id del grupo de enfunde
                    $empleados_sin_enfunde = Empleado::whereNotIn('id', $empleados)
                        ->whereNotIn('id', $reelevos)
                        ->where('idhacienda', $enfunde->idhacienda)
                        ->whereHas('inventario', function ($query) use ($enfunde) {
                            $query->where('idcalendar', $enfunde->idcalendar);
                            $query->whereHas('material', function ($query) {
                                $query->whereHas('getGrupo', function ($query) {
                                    $query->where('id', 1);
                                });
                            });
                        })
                        ->get()->pluck('id')->toArray();


                    $empleados_semana = array_merge($empleados, $reelevos, $empleados_sin_enfunde);
                    $futuro = strtotime(str_replace('/', '-', $enfunde->fecha . "+ 7 days"));
                    $fecha_fut = date(config('constants.date'), $futuro);
                    $calendario_fut = Calendario::where('fecha', $fecha_fut)->first();

                    $empleados_traspasos = array();
                    foreach ($empleados_semana as $empleado):
                        $inventarios_empleado = InventarioEmpleado::where([
                            'idempleado' => $empleado,
                            'idcalendar' => $enfunde->idcalendar,
                        ])->get();

                        $egreso = EgresoBodega::where([
                            'idempleado' => $empleado,
                            'idcalendario' => $enfunde->idcalendar
                        ])->first();

                        if (is_object($egreso)) {
                            $egreso->estado = false;
                            $egreso->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $egreso->save();
                        }

                        foreach ($inventarios_empleado as $inventario):
                            $inventario_empleado = InventarioEmpleado::where([
                                'idempleado' => $empleado,
                                'idmaterial' => $inventario->idmaterial,
                                'idcalendar' => $inventario->idcalendar
                            ])->first();

                            if (is_object($inventario_empleado)) {
                                $inventario_empleado->estado = 0;
                                $inventario_empleado->save();
                                if ($inventario_empleado->sld_final > 0 || $inventario_empleado->tot_devolucion == 0) {
                                    //Si ya se ha registrado un despacho
                                    $inventarios_empleado_siguiente_semana = InventarioEmpleado::where([
                                        'idempleado' => $empleado,
                                        'idcalendar' => $calendario_fut->codigo,
                                        'idmaterial' => $inventario_empleado->idmaterial
                                    ])->first();

                                    if (!is_object($inventarios_empleado_siguiente_semana) && empty($inventarios_empleado_siguiente_semana)) {
                                        //Lo pasamos a la siguiente semana
                                        $inventarios_empleado_siguiente_semana = new InventarioEmpleado();
                                        $inventarios_empleado_siguiente_semana->codigo = $this->codigoTransaccionInventario($enfunde->idhacienda);
                                        $inventarios_empleado_siguiente_semana->idempleado = $empleado;
                                        $inventarios_empleado_siguiente_semana->idmaterial = $inventario_empleado->idmaterial;
                                        $inventarios_empleado_siguiente_semana->idcalendar = $calendario_fut->codigo;
                                        $inventarios_empleado_siguiente_semana->tot_egreso = 0;
                                        $inventarios_empleado_siguiente_semana->tot_devolucion = 0;
                                        $inventarios_empleado_siguiente_semana->created_at = Carbon::now()->format(config('constants.format_date'));
                                    }

                                    $inventarios_empleado_siguiente_semana->sld_inicial = $inventario_empleado->sld_final;
                                    $inventarios_empleado_siguiente_semana->sld_final = (+$inventarios_empleado_siguiente_semana->sld_inicial + +$inventarios_empleado_siguiente_semana->tot_egreso) - $inventarios_empleado_siguiente_semana->tot_devolucion;
                                    $inventarios_empleado_siguiente_semana->updated_at = Carbon::now()->format(config('constants.format_date'));
                                    $inventarios_empleado_siguiente_semana->save();
                                }
                            }
                        endforeach;

                        $empleado_procesado = Empleado::select('id', 'nombres')
                            ->with(['inventario' => function ($query) use ($calendario_fut) {
                                $query->where('idcalendar', $calendario_fut->codigo);
                                $query->where('sld_inicial', '>', 0);
                                $query->select('id', 'idempleado', 'idmaterial', 'sld_inicial', 'estado');
                                $query->with(['material' => function ($query) {
                                    $query->select('id', 'codigo', 'descripcion');
                                }]);
                            }])
                            ->whereHas('inventario', function ($query) use ($calendario_fut) {
                                $query->where('idcalendar', $calendario_fut->codigo);
                                $query->where('sld_inicial', '>', 0);
                            })
                            ->where('id', $empleado)
                            ->first();
                        array_push($empleados_traspasos, $empleado_procesado);
                    endforeach;

                    $enfunde->futuro = true;
                    $enfunde->cerrado += 1;
                    $this->out = $this->respuesta_json('success', 200, 'Enfunde Futuro cerrado con exito');
                    $this->out['transfers'] = $empleados_traspasos;
                }

                if ($enfunde->cerrado == 0)
                    $enfunde->cerrado = 1;

                $enfunde->updated_at = Carbon::now()->format(config('constants.format_date'));
                $enfunde->save();
                DB::commit();
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
                $secciones = LoteSeccionLaborEmpDet::select('id', 'idcabecera')
                    ->where('idcabecera', $secciones_empleado->id)
                    ->get()->pluck('id');

                $totalSaldo = InventarioEmpleado::select(DB::raw('sum(sld_final) as saldo'))
                    ->where([
                        'idempleado' => $idempleado,
                        'idcalendar' => $idcalendar
                    ])->first();

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

                        if (!is_null($detalle->idreelevo)) {
                            $reelevo = Empleado::select('id', 'idhacienda', 'cedula', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'nombres')
                                ->where(['id' => $detalle->idreelevo])->first();
                        }

                        $inventario = InventarioEmpleado::select('id', 'idcalendar', 'idempleado', 'idmaterial', 'sld_inicial', 'tot_egreso', 'tot_devolucion', 'sld_final', 'estado')
                            ->where([
                                'idempleado' => !is_null($detalle->idreelevo) ? $reelevo->id : $idempleado,
                                'idmaterial' => $detalle->idmaterial,
                                'idcalendar' => $idcalendar,
                            ])->with(['material' => function ($query) {
                                $query->select('id', 'descripcion', 'stock');
                            }])->first();

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
                $this->out['saldoEmpleado'] = $totalSaldo;
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
                            'idseccion' => $item->seccion,
                            'idreelevo' => !is_null($item->reelevo) ? $item->reelevo->id : null
                        ])->first();


                        if (is_object($enfunde_detalle)) {
                            $seccion_empleado = LoteSeccionLaborEmpDet::where(['id' => $item->seccion])
                                ->with(['cabSeccionLabor' => function ($query) {
                                    $query->select('id', 'idempleado');
                                }])
                                ->first();

                            if (is_object($seccion_empleado)) {
                                //Inventario
                                $empleado = $seccion_empleado->cabSeccionLabor->idempleado;

                                if (!is_null($item->reelevo)) {
                                    $empleado = $item->reelevo->id;
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

    public function informeSemanalEnfunde(Request $request)
    {
        try {
            $enfunde = Enfunde::groupBy('HAC_ENFUNDES.id', 'HAC_ENFUNDES.idcalendar', 'HAC_ENFUNDES.idhacienda',
                'calendario.semana', 'calendario.periodo', 'calendario.color', 'HAC_ENFUNDES.fecha')
                ->rightJoin('HAC_DET_ENFUNDES as detalle', 'detalle.idenfunde', 'HAC_ENFUNDES.id')
                ->leftJoin('SIS_CALENDARIO_DOLE as calendario', 'calendario.fecha', 'HAC_ENFUNDES.fecha')
                ->select('HAC_ENFUNDES.id', 'HAC_ENFUNDES.idcalendar', 'HAC_ENFUNDES.idhacienda',
                    'calendario.semana', 'calendario.periodo',
                    'calendario.color as colPresente',
                    DB::raw('(SELECT color FROM SIS_CALENDARIO_DOLE WHERE fecha = DATEADD(DAY, 7, HAC_ENFUNDES.fecha)) as colFuturo'),
                    DB::raw('sum(detalle.cant_pre) presente'),
                    DB::raw('sum(detalle.cant_fut) futuro'),
                    DB::raw('sum(detalle.cant_desb) desbunche')
                )
                ->with(['hacienda' => function ($query) {
                    $query->select('id', 'detalle');
                }])
                ->orderBy('HAC_ENFUNDES.idcalendar', 'desc')
                ->orderBy('HAC_ENFUNDES.idhacienda')
                ->paginate(10);


            return response()->json($enfunde, 200);
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function informeSemanalEnfundeMaterial(Request $request)
    {
        try {
            $idenfunde = $request->get('id');
            $materiales = Enfunde::groupBy('HAC_ENFUNDES.id', 'HAC_ENFUNDES.idcalendar', 'material.id', 'material.codigo', 'material.descripcion')
                ->rightJoin('HAC_DET_ENFUNDES as detalle', 'detalle.idenfunde', 'HAC_ENFUNDES.id')
                ->rightJoin('BOD_MATERIALES as material', 'material.id', 'detalle.idmaterial')
                ->select('HAC_ENFUNDES.id', 'HAC_ENFUNDES.idcalendar', 'material.id as idmaterial', 'material.codigo', 'material.descripcion',
                    DB::raw('sum(detalle.cant_pre) as presente'),
                    DB::raw('sum(detalle.cant_fut) as futuro'))
                ->where(['HAC_ENFUNDES.id' => $idenfunde])
                ->get();

            if (count($materiales) > 0) {
                foreach ($materiales as $material):
                    $invenario = InventarioEmpleado::select(DB::raw('ISNULL(sum(tot_egreso),0) as despacho'),
                        DB::raw('ISNULL(sum(sld_inicial),0) as inicio'),
                        DB::raw('ISNULL(sum(sld_final),0) as final')
                    )->where([
                        'idmaterial' => $material->idmaterial,
                        'idcalendar' => $material->idcalendar
                    ])->first();

                    $material->inicial = $invenario->inicio;
                    $material->despacho = $invenario->despacho;
                    $material->final = $invenario->final;
                endforeach;
            }

            return response()->json($materiales, 200);
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function informeSemanalEnfundeEmpleados(Request $request)
    {
        try {
            $idenfunde = $request->get('id');
            $empleados = Enfunde::groupBy('HAC_ENFUNDES.id', 'HAC_ENFUNDES.idcalendar', 'empleado.id', 'empleado.codigo', 'empleado.nombres')
                ->rightJoin('HAC_DET_ENFUNDES as detalle', 'detalle.idenfunde', 'HAC_ENFUNDES.id')
                ->rightJoin('HAC_LOTSEC_LABEMPLEADO_DET as seccion', 'seccion.id', 'detalle.idseccion')
                ->rightJoin('HAC_LOTSEC_LABEMPLEADO as cabeceraSeccion', 'cabeceraSeccion.id', 'seccion.idcabecera')
                ->rightJoin('HAC_EMPLEADOS as empleado', 'empleado.id', 'cabeceraSeccion.idempleado')
                ->select('HAC_ENFUNDES.id', 'HAC_ENFUNDES.idcalendar', 'empleado.id as idempleado', 'empleado.codigo', 'empleado.nombres',
                    DB::raw('sum(detalle.cant_pre) as presente'),
                    DB::raw('sum(detalle.cant_fut) as futuro'))
                ->where(['HAC_ENFUNDES.id' => $idenfunde])
                ->get();
            return response()->json($empleados, 200);
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function informeSmanalEnfundeEmpleadoMaterial(Request $request)
    {
        try {
            $idenfunde = $request->get('id');
            $calendario = $request->get('calendario');
            $empleado = $request->get('empleado');

            $reelevo = $request->get('reelevo');

            $materiales = Enfunde::groupBy('enfunde_det.idmaterial', 'material.descripcion', 'enfunde_det.idreelevo', 'empleado.nombres')
                ->leftJoin('HAC_DET_ENFUNDES as enfunde_det', function ($join) use ($reelevo) {
                    $join->on('enfunde_det.idenfunde', 'HAC_ENFUNDES.id');
                    if (!is_null($reelevo) && filter_var($reelevo, FILTER_VALIDATE_BOOLEAN)) {
                        $join->where('enfunde_det.reelevo', 1);
                    } else {
                        $join->where('enfunde_det.reelevo', 0);
                    }
                })
                ->join('HAC_LOTSEC_LABEMPLEADO_DET as seccion_det', 'seccion_det.id', 'enfunde_det.idseccion')
                ->join('HAC_LOTSEC_LABEMPLEADO as seccion', function ($join) use ($empleado) {
                    $join->on('seccion.id', 'seccion_det.idcabecera');
                    $join->where('idempleado', $empleado);
                })
                ->leftJoin('BOD_MATERIALES as material', 'material.id', 'enfunde_det.idmaterial')
                ->leftJoin('HAC_EMPLEADOS as empleado', 'empleado.id', 'enfunde_det.idreelevo')
                ->select('enfunde_det.idmaterial', 'material.descripcion', 'enfunde_det.idreelevo', 'empleado.nombres',
                    DB::raw('sum(enfunde_det.cant_pre) as presente'),
                    DB::raw('sum(enfunde_det.cant_fut) as futuro')
                )
                ->where([
                    'HAC_ENFUNDES.idcalendar' => $calendario,
                    'HAC_ENFUNDES.id' => $idenfunde
                ])->get();


            if (count($materiales) > 0) {
                foreach ($materiales as $material):

                    if (!is_null($reelevo) && filter_var($reelevo, FILTER_VALIDATE_BOOLEAN)) {
                        $empleado = $material->idreelevo;
                    }

                    $egresos = EgresoBodega::groupBy('egreso_det.idmaterial')
                        ->leftJoin('BOD_DET_EGRESOS as egreso_det', function ($join) use ($material) {
                            $join->on(['egreso_det.idegreso' => 'BOD_EGRESOS.id']);
                            $join->where(['idmaterial' => $material->idmaterial]);
                        })
                        ->select('egreso_det.idmaterial', DB::raw('sum(egreso_det.cantidad) as cantidad'))
                        ->where([
                            'BOD_EGRESOS.idcalendario' => $calendario,
                            'BOD_EGRESOS.idempleado' => $empleado,
                        ])->first();

                    $material->despacho = 0;

                    if (is_object($egresos)) {
                        $material->despacho = $egresos->cantidad;
                    }

                    $inventario = InventarioEmpleado::select('id', 'idmaterial', 'sld_inicial', 'tot_egreso', 'tot_devolucion', 'sld_final')
                        ->where([
                            'idcalendar' => $calendario,
                            'idempleado' => $empleado,
                            'idmaterial' => $material->idmaterial
                        ])->first();

                    $material->inventario = null;
                    if (is_object($inventario)) {
                        $material->inventario = $inventario;
                    }
                endforeach;
            }

            return response()->json($materiales, 200);
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function enfundeSemanal_PDF()
    {
        $pdf = \PDF::loadView('Informes.Hacienda.Labor.Enfunde.enfundeSemanal');
        return $pdf->download('ejemplo.pdf');
        //return view('Informes.Hacienda.Labor.Enfunde.enfundeSemanal');
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
