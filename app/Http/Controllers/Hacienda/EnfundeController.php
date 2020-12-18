<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Bodega\EgresoBodega;
use App\Models\Hacienda\Empleado;
use App\Models\Hacienda\Enfunde;
use App\Models\Hacienda\EnfundeDet;
use App\Models\Hacienda\Hacienda;
use App\Models\Hacienda\InventarioEmpleado;
use App\Models\Hacienda\LoteSeccion;
use App\Models\Hacienda\LoteSeccionLaborEmp;
use App\Models\Hacienda\LoteSeccionLaborEmpDet;
use App\Models\Sistema\Calendario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Helpers\InformePDF;

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
            'dashboardEnfundePeriodo', 'dashboardEnfundeLoteHacienda', 'dashboardEnfundeLoteLotero',
            'dashboardEnfundeHacienda', 'dashboardEnfundeHistorico', 'dashboardEnfundeHectareas', 'dashboardEnfundeSemanalLote',
            'getLoterosLoteEnfunde', 'getLotesLoteroEnfunde',
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

            $loteros_data = function ($hacienda) {
                return LoteSeccionLaborEmp::from('HAC_LOTSEC_LABEMPLEADO as sec')
                    ->leftJoin('HAC_LOTSEC_LABEMPLEADO_DET as secd', 'secd.idcabecera', 'sec.id')
                    ->leftJoin('HAC_EMPLEADOS as empleado', 'empleado.id', 'sec.idempleado')
                    ->leftJoin('HACIENDAS as hacienda', 'hacienda.id', 'empleado.idhacienda')
                    ->select('empleado.id', 'empleado.codigo', 'hacienda.id as idhacienda', 'hacienda.detalle as hacienda',
                        'empleado.nombre1', 'empleado.nombre2', 'empleado.apellido1', 'empleado.apellido2', 'empleado.nombres')
                    ->groupBy('empleado.id', 'empleado.codigo', 'hacienda.id', 'hacienda.detalle',
                        'empleado.nombre1', 'empleado.nombre2', 'empleado.apellido1', 'empleado.apellido2', 'empleado.nombres')
                    ->where([
                        'secd.estado' => true,
                        'sec.idlabor' => 3,
                        'empleado.idhacienda' => $hacienda
                    ]);
            };

            $loteros = $loteros_data($hacienda);
            $loteros_pendientes = $loteros_data($hacienda);

            if (!empty($empleado) && isset($empleado) && !is_null($empleado)) {
                $loteros = $loteros_data($hacienda)->where([
                    'empleado.id' => $empleado
                ]);
            }

            $this->out['dataArray'] = [];

            $loteros_pendientes = $loteros_pendientes->get()->toArray();
            $loteros = $loteros->paginate(5);

            if (count($loteros) > 0) {

                $enfunde_data = function ($calendario, $lotero) {
                    return Enfunde::from('HAC_ENFUNDES as enfunde')
                        ->where([
                            'enfunde.idcalendar' => $calendario,
                            'empleado.id' => $lotero
                        ])
                        ->leftJoin('HAC_DET_ENFUNDES as enfdet', 'enfdet.idenfunde', 'enfunde.id')
                        ->leftJoin('HAC_LOTSEC_LABEMPLEADO_DET as secdet', 'secdet.id', 'enfdet.idseccion')
                        ->leftJoin('HAC_LOTSEC_LABEMPLEADO as sec', 'sec.id', 'secdet.idcabecera')
                        ->leftJoin('HAC_EMPLEADOS as empleado', 'empleado.id', 'sec.idempleado')
                        ->select(DB::raw("sum(enfdet.cant_pre) as presente"), DB::raw("sum(enfdet.cant_fut) as futuro"));
                };

                foreach ($loteros as $lotero):
                    $lotero['total'] = 0;
                    $lotero['presente'] = false;
                    $lotero['futuro'] = false;
                    $lotero['enfunde'] = 0;

                    $lotero['total'] = InventarioEmpleado::where([
                        'idcalendar' => $codigoCalendar,
                        'idempleado' => $lotero['id'],
                        'estado' => true
                    ])->get()->sum('sld_final');

                    $enfunde = $enfunde_data($codigoCalendar, $lotero['id'])->first();

                    if (!empty($enfunde)) {
                        if ($enfunde->presente > 0) {
                            $lotero['presente'] = true;
                            $lotero['enfunde'] = $enfunde->presente;
                            if ($enfunde->futuro > 0) {
                                $lotero['futuro'] = true;
                                $lotero['enfunde'] += $enfunde->futuro;
                            }
                        }
                    }

                endforeach;

                foreach ($loteros_pendientes as $key => $pendiente) {
                    $enfunde = $enfunde_data($codigoCalendar, $pendiente['id'])->first();
                    if (!empty($enfunde)) {
                        if ($enfunde->presente > 0 && $enfunde->futuro > 0) {
                            unset($loteros_pendientes[$key]);
                        }
                    }
                }


                $this->out = $this->respuesta_json('success', 200, 'Loteros encontrados');
                $this->out['dataArrayPendientes'] = array_values($loteros_pendientes);
                $this->out['dataArray'] = $loteros;
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
                $inventario['tot_consumo'] = 0;
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
                    if ($inventario->tot_consumo >= $cantidad) {
                        $inventario->tot_consumo = ($inventario->tot_consumo - $cantidad) + $semana['cantidad'];
                    } else {
                        $inventario->tot_consumo += $semana['cantidad'];
                    }
                    $inventario->sld_final = ($inventario->sld_inicial + $inventario->tot_egreso) - $inventario->tot_consumo;
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
                $inventario->tot_consumo += intval($cantidad);
                $inventario->sld_final = (+$inventario->sld_inicial + $inventario->tot_egreso) - $inventario->tot_consumo;
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
            $enfunde = Enfunde::where('id', $id)->first(); //Buscamos la cabecera del enfunde
            if (is_object($enfunde)) {
                DB::beginTransaction();
                if ($enfunde->presente == 0) {
                    //Primer cierre Presente
                    $enfunde->presente = true;
                    $enfunde->cerrado = 1;
                    $enfunde->updated_at = Carbon::now()->format(config('constants.format_date'));
                    $enfunde->save();

                    $this->out = $this->respuesta_json('success', 200, 'Enfunde Presente cerrado con exito');
                    $this->out['enfundePresente'] = [
                        "presente" => "Se ha reportado el enfunde Presente Correctamente"
                    ];
                } else if ($enfunde->futuro == 0) {
                    $empleados = EnfundeDet::groupBy('seccion.idempleado')
                        ->join('HAC_LOTSEC_LABEMPLEADO_DET as sec_det', 'sec_det.id', 'HAC_DET_ENFUNDES.idseccion')
                        ->join('HAC_LOTSEC_LABEMPLEADO as seccion', 'seccion.id', 'sec_det.idcabecera')
                        ->select('seccion.idempleado')
                        ->where([
                            'idenfunde' => $enfunde->id,
                            'idreelevo' => null
                        ])->get()->pluck('idempleado')->toArray();

                    $reelevos = EnfundeDet::groupBy('empleado.id')
                        ->join('HAC_EMPLEADOS as empleado', 'empleado.id', 'HAC_DET_ENFUNDES.idreelevo')
                        ->select('empleado.id')
                        ->where([
                            'idenfunde' => $enfunde->id,
                            'reelevo' => 1
                        ])->get()->pluck('id')->toArray();

                    //1 es el Id del grupo de enfunde
                    //Empleados con despachos pero sin reportar enfunde
                    $empleados_sin_enfunde = Empleado::whereNotIn('id', $empleados)
                        ->whereNotIn('id', $reelevos)
                        ->where('idhacienda', $enfunde->idhacienda)
                        ->whereHas('inventario', function ($query) use ($enfunde) {
                            $query->where([
                                'idcalendar' => $enfunde->idcalendar,
                                ['sld_final', '>', 0]
                            ]);
                            $query->whereHas('material', function ($query) {
                                $query->whereHas('getGrupo', function ($query) {
                                    //Grupo de enfunde en la base de datos
                                    $query->where('id', 1);
                                });
                            });
                        })
                        ->get()->pluck('id')->toArray();

                    //Una vez que se han seleccionado todos los empleados, se procede a mover el inventario

                    $empleados_semana = array_merge($empleados, $reelevos, $empleados_sin_enfunde);
                    $futuro = strtotime(str_replace('/', '-', $enfunde->fecha . "+ 7 days"));
                    $fecha_fut = date(config('constants.date'), $futuro);
                    $calendario_fut = Calendario::where('fecha', $fecha_fut)->first();

                    $empleados_traspasos = array();
                    foreach ($empleados_semana as $empleado):
                        //Se cierran los despachos de bodega
                        $egreso = EgresoBodega::from('BOD_EGRESOS as egreso')
                            ->select('egreso.id', 'egreso.fecha_apertura', 'egreso.idempleado', 'egreso.parcial', 'egreso.final', 'egreso.estado')
                            ->join('SIS_CALENDARIO_DOLE AS calendario', 'calendario.fecha', 'egreso.fecha_apertura')
                            ->where([
                                'idempleado' => $empleado,
                                'calendario.codigo' => $enfunde->idcalendar
                            ])->first();

                        if (is_object($egreso)) {
                            $egreso->estado = false;
                            $egreso->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $egreso->save();
                        }

                        $inventarios_empleado = InventarioEmpleado::where([
                            'idempleado' => $empleado,
                            'idcalendar' => $enfunde->idcalendar,
                            ['sld_final', '>', 0]
                        ])->get();

                        if (count($inventarios_empleado) > 0):
                            foreach ($inventarios_empleado as $inventario):
                                $inventario_empleado = InventarioEmpleado::where([
                                    'idempleado' => $empleado,
                                    'idmaterial' => $inventario->idmaterial,
                                    'idcalendar' => $inventario->idcalendar
                                ])->first();

                                if (is_object($inventario_empleado)) {
                                    $inventario_empleado->estado = 0;
                                    $inventario_empleado->save();
                                    if ($inventario_empleado->sld_final > 0 || $inventario_empleado->tot_consumo == 0) {
                                        //Si ya se ha registrado un despacho
                                        $inventarios_empleado_siguiente_semana = InventarioEmpleado::where([
                                            'idempleado' => $empleado,
                                            'idcalendar' => $calendario_fut->codigo,
                                            'idmaterial' => $inventario_empleado->idmaterial
                                        ])->first();

                                        if (!is_object($inventarios_empleado_siguiente_semana)) {
                                            //Lo pasamos a la siguiente semana
                                            $inventarios_empleado_siguiente_semana = new InventarioEmpleado();
                                            //$inventarios_empleado_siguiente_semana->codigo = $this->codigoTransaccionInventario($enfunde->idhacienda);
                                            $inventarios_empleado_siguiente_semana->idempleado = $empleado;
                                            $inventarios_empleado_siguiente_semana->idmaterial = $inventario_empleado->idmaterial;
                                            $inventarios_empleado_siguiente_semana->idcalendar = $calendario_fut->codigo;
                                            $inventarios_empleado_siguiente_semana->tot_egreso = 0;
                                            $inventarios_empleado_siguiente_semana->tot_consumo = 0;
                                            $inventarios_empleado_siguiente_semana->tot_devolucion = 0;
                                            $inventarios_empleado_siguiente_semana->created_at = Carbon::now()->format(config('constants.format_date'));
                                        }

                                        $inventarios_empleado_siguiente_semana->sld_inicial = intval($inventario_empleado->sld_final);
                                        $inventarios_empleado_siguiente_semana->saldoFinal();
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
                        endif;
                    endforeach;

                    $enfunde->futuro = true;
                    $enfunde->cerrado += 1;
                    $enfunde->updated_at = Carbon::now()->format(config('constants.format_date'));
                    $enfunde->save();

                    $this->out = $this->respuesta_json('success', 200, 'Enfunde Futuro cerrado con exito');
                    $this->out['transfers'] = $empleados_traspasos;

                } else {
                    $this->out = $this->respuesta_json('success', 200, 'Ya se cerro esta semana de enfunde.');
                }

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
            $idempleado = $request->get('empleado');

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
                    ])->with(['detalle' => function ($query) use ($secciones) {
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

                $enfunde_status = new \stdClass();
                $enfunde_status->status_presente = (bool)$enfundeDetalle->presente;
                $enfunde_status->status_futuro = (bool)$enfundeDetalle->futuro;

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

                        $inventario = InventarioEmpleado::select('id', 'idcalendar', 'idempleado', 'idmaterial', 'sld_inicial', 'tot_egreso', 'tot_consumo', 'sld_final', 'estado')
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
                $this->out['dataEnfunde'] = $enfunde_status;
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

                                $inventario->tot_consumo = $inventario->tot_consumo - $item->cantidad;
                                $inventario->sld_final = ($inventario->sld_inicial + $inventario->tot_egreso) - $inventario->tot_consumo;
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
            $hacienda = $request->get('hacienda');
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
                ->orderBy('HAC_ENFUNDES.idhacienda');

            if (!empty($hacienda) && isset($hacienda) && !is_null($hacienda)) {
                $enfunde = $enfunde->where([
                    'HAC_ENFUNDES.idhacienda' => $hacienda
                ]);
            }

            $enfunde = $enfunde->paginate(10);

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
                        ->join('SIS_CALENDARIO_DOLE AS calendario', 'calendario.fecha', 'BOD_EGRESOS.fecha_apertura')
                        ->leftJoin('BOD_DET_EGRESOS as egreso_det', function ($join) use ($material) {
                            $join->on(['egreso_det.idegreso' => 'BOD_EGRESOS.id']);
                            $join->where(['idmaterial' => $material->idmaterial]);
                        })
                        ->select('egreso_det.idmaterial', DB::raw('sum(egreso_det.cantidad) as cantidad'))
                        ->where([
                            'calendario.codigo' => $calendario,
                            'BOD_EGRESOS.idempleado' => $empleado,
                        ])->first();

                    $material->despacho = 0;

                    if (is_object($egresos)) {
                        $material->despacho = $egresos->cantidad;
                    }

                    $inventario = InventarioEmpleado::select('id', 'idmaterial', 'sld_inicial', 'tot_egreso', 'tot_consumo', 'sld_final')
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

    public function enfundeSemanal_PDF($id, Request $request)
    {
        $data = Enfunde::where(['id' => $id])->first();
        $extension = $request->get('extension');
        $lotes = $request->get('lotes');
        $material_saldos = $request->get('saldos');
        if ($extension !== null):
            if (is_object($data)):
                if ($lotes !== null || $material_saldos !== null):
                    $hacienda = Hacienda::where(['id' => $data->idhacienda])->first();
                    $calendario = Calendario::getCalendario($data->fecha);
                    $detalle = Enfunde::groupBy('HAC_ENFUNDES.id', 'HAC_ENFUNDES.idcalendar', 'empleado.id', 'empleado.codigo', 'empleado.nombres')
                        ->rightJoin('HAC_DET_ENFUNDES as detalle', 'detalle.idenfunde', 'HAC_ENFUNDES.id')
                        ->rightJoin('HAC_LOTSEC_LABEMPLEADO_DET as seccion', 'seccion.id', 'detalle.idseccion')
                        ->rightJoin('HAC_LOTSEC_LABEMPLEADO as cabeceraSeccion', 'cabeceraSeccion.id', 'seccion.idcabecera')
                        ->rightJoin('HAC_EMPLEADOS as empleado', 'empleado.id', 'cabeceraSeccion.idempleado')
                        ->select('HAC_ENFUNDES.id as idenfunde', 'HAC_ENFUNDES.idcalendar', 'empleado.id as idempleado', 'empleado.codigo', 'empleado.nombres',
                            DB::raw('sum(detalle.cant_pre) as presente'),
                            DB::raw('sum(detalle.cant_fut) as futuro'))
                        ->where(['HAC_ENFUNDES.id' => $data->id])
                        ->get();

                    if (count($detalle) > 0) {
                        if ($lotes !== null):
                            foreach ($detalle as $lotero):
                                //Seccion y enfunde
                                $lotero->lotes = EnfundeDet::from('HAC_DET_ENFUNDES as detalle')
                                    ->where(['idenfunde' => $lotero->idenfunde])
                                    ->groupBy('lseccion.alias', 'detalle.idreelevo')
                                    ->select(DB::raw("RIGHT('' + LTRIM(lseccion.alias), 3) alias"),
                                        DB::raw("sum(cant_pre) cant_pre"),
                                        DB::raw("sum(cant_fut) cant_fut"), 'detalle.idreelevo')
                                    ->join('HAC_LOTSEC_LABEMPLEADO_DET as seccion', 'seccion.id', 'detalle.idseccion')
                                    ->join('HAC_LOTSEC_LABEMPLEADO as cabeceraSeccion', 'cabeceraSeccion.id', 'seccion.idcabecera')
                                    ->join('HAC_LOTES_SECCION as lseccion', 'lseccion.id', 'seccion.idlote_sec')
                                    ->join('HAC_EMPLEADOS as empleado', 'empleado.id', 'cabeceraSeccion.idempleado')
                                    ->where([
                                        'empleado.id' => $lotero->idempleado
                                    ])
                                    ->with(['reelevo' => function ($query) {
                                        $query->select('id', 'codigo', 'cedula', 'nombres', 'nombre1', 'nombre2', 'apellido1', 'apellido2');
                                    }])
                                    ->orderByRaw("RIGHT('' + LTRIM(lseccion.alias), 3)")
                                    ->get();
                            endforeach;
                        elseif ($material_saldos !== null):
                            $empleados = EnfundeDet::groupBy('seccion.idempleado')
                                ->join('HAC_LOTSEC_LABEMPLEADO_DET as sec_det', 'sec_det.id', 'HAC_DET_ENFUNDES.idseccion')
                                ->join('HAC_LOTSEC_LABEMPLEADO as seccion', 'seccion.id', 'sec_det.idcabecera')
                                ->select('seccion.idempleado')
                                ->where([
                                    'idenfunde' => $data->id,
                                    'idreelevo' => null
                                ])->get()->pluck('idempleado')->toArray();

                            $reelevos = EnfundeDet::groupBy('empleado.id')
                                ->join('HAC_EMPLEADOS as empleado', 'empleado.id', 'HAC_DET_ENFUNDES.idreelevo')
                                ->select('empleado.id')
                                ->where([
                                    'idenfunde' => $data->id,
                                    'reelevo' => 1
                                ])->get()->pluck('id')->toArray();

                            //1 es el Id del grupo de enfunde
                            //Empleados con despachos pero sin reportar enfunde
                            $empleados_sin_enfunde = Empleado::whereNotIn('id', $empleados)
                                ->whereNotIn('id', $reelevos)
                                ->where('idhacienda', $data->idhacienda)
                                ->whereHas('inventario', function ($query) use ($data) {
                                    $query->where([
                                        'idcalendar' => $data->idcalendar,
                                        //['sld_final', '>', 0]
                                    ]);
                                    $query->whereHas('material', function ($query) {
                                        $query->whereHas('getGrupo', function ($query) {
                                            //Grupo de enfunde en la base de datos
                                            $query->where('id', 1);
                                        });
                                    });
                                })
                                ->get()->pluck('id')->toArray();

                            //Una vez que se han seleccionado todos los empleados, se procede a mover el inventario

                            $empleados_semana = array_merge($empleados, $reelevos, $empleados_sin_enfunde);
                            //Materiales saldo
                            $detalle = InventarioEmpleado::where([
                                'idcalendar' => $data->idcalendar,
                                //['sld_final', '>', 0]
                            ])
                                ->whereIn('idempleado', $empleados_semana)
                                ->select('id', 'idcalendar', 'idempleado', 'idmaterial', 'sld_inicial', 'tot_egreso', 'tot_consumo', 'tot_devolucion', 'sld_final')
                                ->with(['material' => function ($query) {
                                    $query->select('id', 'codigo', 'descripcion');
                                }])
                                ->with(['empleado' => function ($query) {
                                    $query->select('id', 'codigo', 'cedula', 'nombres', 'nombre1', 'nombre2', 'apellido1', 'apellido2');
                                }])
                                ->get();

                        endif;
                    }

                    //return response()->json($detalle);
                    switch ($extension):
                        case 'pdf':
                            if ($lotes !== null):
                                $pdf = new InformePDF("Infome enfunde semanal - Lotero");
                                $fecha = date("d/m/Y");
                                $hora = date("H:i:s");
                                $pdf->cabecera($hacienda->detalle, "Fecha: $fecha\nHora: $hora");
                                $build = $pdf->build();
                                $build->AddPage();
                                $build->SetFont('Helvetica', 'B', 9);
                                $build->Cell(0, 0, 'REPORTE DE ENFUNDE SEMANAL', 0, 1, 'C', 0);
                                $build->Cell(0, 0, "Periodo: " . $calendario->periodo . " - Semana: " . $calendario->semana, 0, 1, 'L', 0);
                                $build->writeHTML("<hr>", true, false, false, false, '');
                                $fill = $build->SetFillColor(230, 230, 230);
                                $build->Cell(10, 0, '#', 0, 0, 'C', $fill);
                                $build->Cell(20, 0, 'Lotes', 0, 0, 'C', $fill);
                                $build->Cell(50, 0, 'Reelevo', 0, 0, 'C', $fill);
                                $build->Cell(40, 0, 'Presente', 0, 0, 'C', $fill);
                                $build->Cell(40, 0, 'Futuro', 0, 0, 'C', $fill);
                                $build->Cell(40, 0, 'Total', 0, 0, 'C', $fill);
                                $build->Ln();

                                $total_Loteros = 0;
                                $total_enfunde_presente = 0;
                                $total_enfunde_futuro = 0;

                                //Indicadores
                                $empleado_maximo = '';
                                $maximo = 0;
                                $empleado_minimo = '';
                                $minimo = 0;

                                foreach ($detalle as $index => $lotero):
                                    $index += 1;
                                    $total_Loteros += 1;
                                    $build->SetFont('Helvetica', 'B', 8);
                                    $build->Cell(200, 0, "Lotero $index: $lotero->nombres", 0, 1, 'L', $fill);
                                    $build->SetFont('Helvetica', '', 8);

                                    $total_presente = 0;
                                    $total_futuro = 0;
                                    foreach ($lotero->lotes as $key => $lote):
                                        $build->Cell(10, 0, $key + 1, 0, 0, 'C');
                                        $build->Cell(20, 0, $lote->alias, 0, 0, 'C');

                                        $build->SetFont('Helvetica', '', 7);
                                        $reelevo = $lote->reelevo ? $lote->reelevo->apellido1 . " " . $lote->reelevo->nombre1 . " " . $lote->reelevo->nombre2 : '';
                                        $build->Cell(10, 0, $lote->reelevo ? $lote->reelevo->codigo : '', 0, 0, 'L');
                                        $build->Cell(40, 0, $reelevo, 0, 0, 'R');
                                        $build->SetFont('Helvetica', '', 8);

                                        $build->Cell(40, 0, $lote->cant_pre, 0, 0, 'C');
                                        $build->Cell(40, 0, $lote->cant_fut, 0, 0, 'C');
                                        $build->Cell(40, 0, $lote->cant_pre + $lote->cant_fut, 0, 0, 'C');
                                        $build->Ln();

                                        $total_presente += $lote->cant_pre;
                                        $total_futuro += $lote->cant_fut;
                                    endforeach;

                                    $fill = $build->SetFillColor(245, 245, 245);
                                    $build->SetFont('Helvetica', 'B', 8);
                                    $build->Cell(80, 0, '', 0, 0, 'C');
                                    $build->Cell(40, 0, $total_presente, 0, 0, 'C', $fill);
                                    $build->Cell(40, 0, $total_futuro, 0, 0, 'C', $fill);
                                    $build->Cell(40, 0, $total_presente + $total_futuro, 0, 0, 'C', $fill);
                                    $fill = $build->SetFillColor(230, 230, 230);
                                    $build->SetFont('Helvetica', '', 8);
                                    $build->Ln();

                                    if ($maximo == 0):
                                        $maximo = $total_presente + $total_futuro;
                                        $empleado_maximo = $lotero->nombres;
                                    elseif ($maximo < ($total_presente + $total_futuro)):
                                        $maximo = $total_presente + $total_futuro;
                                        $empleado_maximo = $lotero->nombres;
                                    endif;

                                    if ($minimo == 0):
                                        $minimo = $total_presente + $total_futuro;
                                        $empleado_minimo = $lotero->nombres;
                                    elseif ($minimo > ($total_presente + $total_futuro)):
                                        $minimo = $total_presente + $total_futuro;
                                        $empleado_minimo = $lotero->nombres;
                                    endif;

                                    $total_enfunde_presente += $total_presente;
                                    $total_enfunde_futuro += $total_futuro;
                                endforeach;

                                $build->Ln();
                                $build->SetFont('Helvetica', 'B', 12);
                                $build->Cell(80, 0, '', 0, 0, 'C');
                                $build->Cell(40, 0, $total_enfunde_presente, 0, 0, 'C', $fill);
                                $build->Cell(40, 0, $total_enfunde_futuro, 0, 0, 'C', $fill);
                                $build->Cell(40, 0, $total_enfunde_presente + $total_enfunde_futuro, 0, 0, 'C', $fill);
                                $build->writeHTML("<hr>", true, false, false, false, '');
                                $build->Ln();

                                $promedio = round(($total_enfunde_presente + $total_enfunde_futuro) / $total_Loteros, 0);
                                $build->Cell(40, 0, "Enfunde Promedio:", 0, 0, 'L');
                                $build->Cell(40, 0, $promedio, 0, 0, 'L');
                                $build->Ln();
                                $build->writeHTML("<hr>", 0, false, false, false, '');
                                $build->Cell(40, 0, "Enfunde Máximo:", 0, 0, 'L');
                                $build->Cell(8, 0, $maximo, 0, 0, 'L');
                                $build->Cell(40, 0, "| $empleado_maximo", 0, 0, 'L');
                                $build->Ln();
                                $build->Cell(40, 0, "Enfunde Mínimo:", 0, 0, 'L');
                                $build->Cell(8, 0, $minimo, 0, 0, 'L');
                                $build->Cell(40, 0, "| $empleado_minimo", 0, 0, 'L');
                                $build->Ln();

                                $pdf->generar("Enfunde-semanal.pdf");

                            elseif ($material_saldos !== null):
                                $pdf = new InformePDF("Infome saldo final - loteros");
                                $fecha = date("d/m/Y");
                                $hora = date("H:i:s");
                                $pdf->cabecera($hacienda->detalle, "Fecha: $fecha\nHora: $hora");
                                $build = $pdf->build();
                                $build->AddPage();
                                $build->SetFont('Helvetica', 'B', 9);
                                $build->Cell(0, 0, 'REPORTE DE SALDO FINAL AL CIERRE DE SEMANA', 0, 1, 'C', 0);
                                $build->Cell(0, 0, "Periodo: " . $calendario->periodo . " - Semana: " . $calendario->semana, 0, 1, 'L', 0);
                                $build->writeHTML("<hr>", true, false, false, false, '');
                                $fill = $build->SetFillColor(230, 230, 230);
                                $build->Cell(15, 0, 'Codigo', 0, 0, 'C', $fill);
                                $build->Cell(65, 0, 'Empleado', 0, 0, 'C', $fill);
                                $build->Cell(80, 0, 'Material', 0, 0, 'C', $fill);
                                $build->Cell(40, 0, 'Saldo', 0, 0, 'C', $fill);
                                $build->Ln();

                                $materiales_usados = array();
                                foreach ($detalle as $index => $item):
                                    $build->SetFont('Helvetica', 'B', 8);
                                    $build->Cell(15, 0, $item->empleado->codigo, 0, 0, 'C');
                                    $build->Cell(65, 0, $item->empleado->nombres, 0, 0, 'L');
                                    $build->SetFont('Helvetica', '', 6);
                                    $build->Cell(15, 0, $item->material->codigo, 0, 0, 'L');
                                    $build->SetFont('Helvetica', '', 7);
                                    $build->Cell(65, 0, $item->material->descripcion, 0, 0, 'L');
                                    $build->Ln();

                                    $build->SetFont('Helvetica', 'B', 8);
                                    $build->Cell(120, 0, "", 0, 0, 'C');
                                    $build->Cell(40, 0, "Saldo Inicial", 0, 0, 'R');
                                    $build->Cell(40, 0, $item->sld_inicial, 0, 0, 'C');
                                    $build->Ln();
                                    $build->Cell(120, 0, "", 0, 0, 'C');
                                    $build->Cell(40, 0, "Total Egreso", 0, 0, 'R');
                                    $build->Cell(40, 0, $item->tot_egreso, 0, 0, 'C');
                                    $build->Ln();
                                    $build->Cell(120, 0, "", 0, 0, 'C');
                                    $build->Cell(40, 0, "Total Consumo", 0, 0, 'R');
                                    $build->Cell(40, 0, $item->tot_consumo, 0, 0, 'C');
                                    $build->Ln();
                                    $build->Cell(120, 0, "", 0, 0, 'C');
                                    $build->Cell(40, 0, "Total Devolucion", 0, 0, 'R');
                                    $build->Cell(40, 0, $item->tot_devolucion, 0, 0, 'C');
                                    $build->Ln();
                                    $build->Cell(120, 0, "", 0, 0, 'C', $fill);
                                    $build->Cell(40, 0, "Saldo Final", 0, 0, 'R', $fill);
                                    $build->Cell(40, 0, $item->sld_final, 0, 0, 'C', $fill);
                                    $build->Ln();

                                    $material = new \stdClass();
                                    $material->codigo = $item->material->codigo;
                                    $material->descripcion = $item->material->descripcion;
                                    $material->sld_inicial = $item->sld_inicial;
                                    $material->tot_egreso = $item->tot_egreso;
                                    $material->tot_consumo = $item->tot_consumo;
                                    $material->tot_devolucion = $item->tot_devolucion;
                                    $material->sld_final = $item->sld_final;

                                    if (count($materiales_usados) == 0):
                                        array_push($materiales_usados, $material);
                                    else:
                                        $existe = false;
                                        $index = 0;
                                        foreach ($materiales_usados as $i => $item_mat):
                                            if ($item_mat->codigo == $material->codigo):
                                                $existe = true;
                                                $index = $i;
                                                break;
                                            endif;
                                        endforeach;

                                        if (!$existe):
                                            array_push($materiales_usados, $material);
                                        else:
                                            $materiales_usados[$index]->sld_inicial += $material->sld_inicial;
                                            $materiales_usados[$index]->tot_egreso += $material->tot_egreso;
                                            $materiales_usados[$index]->tot_consumo += $material->tot_consumo;
                                            $materiales_usados[$index]->tot_devolucion += $material->tot_devolucion;
                                            $materiales_usados[$index]->sld_final += $material->sld_final;
                                        endif;
                                    endif;
                                endforeach;
                                //return var_dump($materiales_usados);
                                $build->writeHTML("<hr>", true, false, false, false, '');
                                if (count($materiales_usados) > 0):
                                    $build->SetFont('Helvetica', 'B', 10);
                                    $build->Cell(100, 0, "SALDO FINAL:", 0, 0, 'L');
                                    $build->Cell(25, 0, "S_INICIAL", 0, 0, 'C');
                                    $build->Cell(25, 0, "DESPACHO", 0, 0, 'C');
                                    $build->Cell(25, 0, "CONSUMO", 0, 0, 'C');
                                    $build->Cell(25, 0, "SALDO", 0, 1, 'C');
                                    $build->writeHTML("<hr>", 0, false, false, false, '');
                                    $build->SetFont('Helvetica', '', 8);

                                    $sld_inicial = 0;
                                    $tot_egreso = 0;
                                    $tot_consumo = 0;
                                    $sld_final = 0;
                                    foreach ($materiales_usados as $material):
                                        $build->Cell(100, 0, $material->descripcion, 0, 0, 'L');
                                        $build->Cell(25, 0, $material->sld_inicial, 0, 0, 'C');
                                        $build->Cell(25, 0, $material->tot_egreso, 0, 0, 'C');
                                        $build->Cell(25, 0, $material->tot_consumo, 0, 0, 'C');
                                        $build->Cell(25, 0, $material->sld_final, 0, 0, 'C');
                                        $build->Ln();

                                        $sld_inicial += $material->sld_inicial;
                                        $tot_egreso += $material->tot_egreso;
                                        $tot_consumo += $material->tot_consumo;
                                        $sld_final += $material->sld_final;
                                    endforeach;
                                    $build->writeHTML("<hr>", 0, false, false, false, '');
                                    $build->SetFont('Helvetica', 'B', 12);
                                    $build->Cell(100, 0, "", 0, 0, 'L');
                                    $build->Cell(25, 0, $sld_inicial, 0, 0, 'C');
                                    $build->Cell(25, 0, $tot_egreso, 0, 0, 'C');
                                    $build->Cell(25, 0, $tot_consumo, 0, 0, 'C');
                                    $build->Cell(25, 0, $sld_final, 0, 0, 'C');
                                    $build->Ln();
                                endif;
                                $pdf->generar("Saldos-Final.pdf");
                            endif;
                            break;
                        default:
                            var_dump("No existe extension.");
                            die;
                    endswitch;
                endif;
            endif;
        endif;

        return "<b>Error!!!</b>, No se puede ejecutar esta consulta.";
        //return view('Informes.Hacienda.Labor.Enfunde.enfundeSemanal');
    }


    public function dashboardEnfundePeriodo(Request $request)
    {
        try {
            $idhacienda = $request->get('idhacienda');
            $periodo = $request->get('periodo');

            $sql = DB::table('SIS_CALENDARIO_DOLE AS calendario')
                ->crossJoin('HACIENDAS AS hacienda')
                ->select('calendario.periodo',
                    DB::raw("'Per ' + RIGHT ( '00' + LTRIM( calendario.periodo ), 2 ) AS per_chart"),
                    'hacienda.id as idhacienda', 'hacienda.detalle',
                    DB::raw("ISNULL((SELECT isnull( SUM ( detalle.cant_pre + detalle.cant_fut ),0) total
                    FROM HAC_ENFUNDES enfunde
                    INNER JOIN HAC_DET_ENFUNDES detalle ON detalle.idenfunde = enfunde.id
                    INNER JOIN HAC_LOTSEC_LABEMPLEADO_DET seccionDet on seccionDet.id = detalle.idseccion
                    INNER JOIN SIS_CALENDARIO_DOLE calendario2 ON calendario2.fecha = enfunde.fecha
                    AND calendario2.periodo = calendario.periodo AND enfunde.idhacienda = hacienda.id), 0) as total")
                )
                ->whereBetween('calendario.codigo', [22000, 22053])
                ->groupBy('calendario.periodo', 'hacienda.id', 'hacienda.detalle');

            if (!empty($idhacienda) && $idhacienda !== "null") {
                $sql = $sql->where('hacienda.id', $idhacienda);
            }

            if (!empty($periodo) && $periodo !== "null") {
                $sql = $sql->where('calendario.periodo', $periodo);
            }

            $sql = $sql->get();

            $this->out = $this->respuesta_json('success', 200, 'Consulta ejecutada');

            //Chart bar
            $values_hacienda = [];
            $options = [];

            $haciendas = Hacienda::getHaciendas();
            if (count($sql->all()) > 0) {
                $categories = true;
                foreach ($haciendas as $hacienda) {
                    $values = [];
                    foreach ($sql->all() as $value) {
                        if ($value->idhacienda == $hacienda->id) {
                            array_push($values, +$value->total);
                            if ($categories)
                                //Las opciones [categories] solo se ponen una vez
                                array_push($options, $value->per_chart);
                        }
                    }
                    $categories = false;
                    array_push($values_hacienda, ['name' => $hacienda->detalle, 'data' => $values]);
                }
            }

            $this->out['categories'] = $options;
            $this->out['data'] = $values_hacienda;

            return response()->json($this->out, 200);
        } catch (\Exception $ex) {
            $this->out = $this->respuesta_json('error', 500, 'Error en la solicitud!!!');
            $this->out['errors'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function dashboardEnfundeLoteHacienda(Request $request)
    {
        try {
            $hacienda = $request->get('idhacienda');
            $periodo = $request->get('periodo');
            $semana = $request->get('semana');

            if ($hacienda !== null) {
                $sql = DB::table('HAC_ENFUNDES AS enfunde')
                    ->select(
                        'seccion.id',
                        DB::raw("RIGHT( '000' + seccion.alias, 3 ) alias"),
                        DB::raw("isnull( SUM ( detalle.cant_pre + detalle.cant_fut ), 0 ) total")
                    )
                    ->join('HAC_DET_ENFUNDES AS detalle', 'detalle.idenfunde', 'enfunde.id')
                    ->join('HAC_LOTSEC_LABEMPLEADO_DET AS seccion_labor_det', 'seccion_labor_det.id', 'detalle.idseccion')
                    ->join('HAC_LOTES_SECCION AS seccion', 'seccion_labor_det.idlote_sec', 'seccion.id')
                    ->join('SIS_CALENDARIO_DOLE AS calendario', function ($query) {
                        $inicio_fin_year = $this->codigoCalendarioAnual(2020);
                        $query->on('calendario.fecha', 'enfunde.fecha')
                            ->whereBetween('calendario.codigo', [$inicio_fin_year->inicio, $inicio_fin_year->fin]);
                    })
                    ->where('enfunde.idhacienda', $hacienda)
                    ->groupBy('seccion.id', 'seccion.alias')
                    ->orderBy(DB::raw("SUM ( detalle.cant_pre + detalle.cant_fut)"), 'desc');

                if (!empty($periodo) && $periodo !== "null") {
                    $sql = $sql->where('calendario.periodo', $periodo);
                }

                if (!empty($semana) && $semana !== "null") {
                    $sql = $sql->where('calendario.semana', $semana);
                }

                $sql = $sql->get();

                $this->out = $this->respuesta_json('success', 200, 'Consulta ejecutada');
                //Chart bar
                $values = [];
                $options = [];
                $id = [];

                if (count($sql->all()) > 0) {
                    foreach ($sql->all() as $value) {
                        array_push($id, $value->id);
                        array_push($values, $value->total);
                        array_push($options, $value->alias);
                    }
                }

                $this->out['valueLotes'] = [
                    'name' => 'Total Lotes',
                    'data' => $values,
                ];

                $this->out['dataId'] = $id;
                $this->out['dataOptions'] = $options;

                return response()->json($this->out, 200);
            }
            throw new \Exception("No se ha recibido el codigo de la hacienda");
        } catch (\Exception $ex) {
            $this->out = $this->respuesta_json('error', 500, 'Error en la solicitud!!!');
            $this->out['errors'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function dashboardEnfundeLoteLotero(Request $request)
    {
        try {
            $hacienda = $request->get('idhacienda');
            $periodo = $request->get('periodo');
            $semana = $request->get('semana');

            if (!empty($hacienda) && !is_null($hacienda)) {
                $sql = DB::table('HAC_ENFUNDES AS enfunde')
                    ->select('empleado.id', 'empleado.nombres',
                        DB::raw("sum(detalle.cant_pre + detalle.cant_fut) as enfunde"),
                        DB::raw("count(distinct enfunde.id) as semanasLaboradas"),
                        DB::raw("round(sum(seccion_empD.has)/count(distinct enfunde.id),2) HasProm"),
                        DB::raw("Round(sum(detalle.cant_pre + detalle.cant_fut)/(sum(seccion_empD.has)/count(distinct enfunde.id)),0) enfundeHas")
                    )
                    ->join('HAC_DET_ENFUNDES AS detalle', 'detalle.idenfunde', 'enfunde.id')
                    ->join('HAC_LOTSEC_LABEMPLEADO_DET AS seccion_empD', 'seccion_empD.id', 'detalle.idseccion')
                    ->join('HAC_LOTSEC_LABEMPLEADO AS seccion_emp', 'seccion_emp.id', 'seccion_empD.idcabecera')
                    ->join('HAC_EMPLEADOS AS empleado', 'empleado.id', 'seccion_emp.idempleado')
                    ->join('SIS_CALENDARIO_DOLE AS calendario', function ($query) {
                        $inicio_fin_year = $this->codigoCalendarioAnual(2020);
                        $query->on('calendario.fecha', 'enfunde.fecha')
                            ->whereBetween('calendario.codigo', [$inicio_fin_year->inicio, $inicio_fin_year->fin]);
                    })
                    ->where('empleado.idhacienda', $hacienda)
                    ->groupBy('empleado.id', 'empleado.nombres')
                    ->orderBy(DB::raw("sum(detalle.cant_pre + detalle.cant_fut)"), "desc");

                if (!empty($periodo) && $periodo !== "null") {
                    $sql = $sql->where('calendario.periodo', $periodo);
                }

                if (!empty($semana) && $semana !== "null") {
                    $sql = $sql->where('calendario.semana', $semana);
                }

                $sql = $sql->get();

                $this->out = $this->respuesta_json('success', 200, 'Consulta ejecutada');
                //$this->out['datos'] = $sql->all();
                /*"nombres": "COELLO CASTILLO JOSE WILLIAN",
                "enfunde": "4920",
                "semanasLaboradas": "13",
                "HasProm": "6.9800000000000004",
                "enfundeHas": "705.0"*/

                $values = [];
                $options = [];
                $id = [];


                if ($sql->count() > 0) {
                    foreach ($sql->all() as $item) {
                        array_push($id, $item->id);
                        array_push($values, $item->enfunde);
                        array_push($options, $item->nombres);
                    }
                }

                $this->out['values'] = [
                    'name' => 'Enfunde Lotero',
                    'data' => $values
                ];
                $this->out['dataId'] = $id;
                $this->out['options'] = $options;

                return response()->json($this->out, 200);
            }
            throw new \Exception("No se ha recibido el codigo de la hacienda");
        } catch (\Exception $ex) {
            $this->out = $this->respuesta_json('error', 500, 'Error en la solicitud!!!');
            $this->out['errors'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function dashboardEnfundeHacienda(Request $request)
    {
        try {

            $idhacienda = $request->get('idhacienda');
            $periodo = $request->get('periodo');

            $sql = DB::table('HAC_ENFUNDES AS enfunde')
                ->select('idhacienda', 'hacienda.detalle',
                    DB::raw("sum(detalle.cant_pre + detalle.cant_fut) enfunde"))
                ->join('HAC_DET_ENFUNDES AS detalle', 'detalle.idenfunde', 'enfunde.id')
                ->join('HACIENDAS AS hacienda', 'hacienda.id', 'enfunde.idhacienda')
                ->groupBy('idhacienda', 'hacienda.detalle')
                ->orderBy('idhacienda');

            if (!empty($idhacienda) && $idhacienda !== "null") {
                $sql = $sql->where('hacienda.id', $idhacienda);
            }

            $sql = $sql->get();

            $this->out = $this->respuesta_json('success', 200, 'Consulta ejecutada');
            //$this->out['datos'] = $sql->all();

            $values = [];
            $options = [];
            $id = [];

            if ($sql->count() > 0) {
                foreach ($sql->all() as $item) {
                    array_push($id, $item->idhacienda);
                    array_push($values, intval($item->enfunde));
                    array_push($options, $item->detalle);
                }
            }

            $this->out['dataHaciendas'] = $sql->all();
            $this->out['values'] = $values;
            $this->out['options'] = $options;
            $this->out['dataId'] = $id;

            return response()->json($this->out, 200);
        } catch (\Exception $ex) {
            $this->out = $this->respuesta_json('error', 500, 'Error en la solicitud!!!');
            $this->out['errors'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function dashboardEnfundeHistorico(Request $request)
    {
        try {
            $idhacienda = $request->get('idhacienda');
            $options = ['2018', '2019', '2020'];

            $sql = DB::table('HAC_ENFUNDES AS enfunde')
                ->select(
                    'hacienda.id', 'hacienda.detalle', DB::raw('SUBSTRING(convert(varchar, calendario.codigo), 1, 3) + 1800 as year'),
                    DB::raw("sum(detalle.cant_pre + detalle.cant_fut) total"))
                ->join('HAC_DET_ENFUNDES AS detalle', 'detalle.idenfunde', 'enfunde.id')
                ->join('HACIENDAS AS hacienda', function ($query) {
                    $query->on('hacienda.id', 'enfunde.idhacienda');
                })
                ->join('SIS_CALENDARIO_DOLE AS calendario', function ($query) {
                    $inicio_fin_year = $this->codigoCalendarioAnual(2020);
                    $query->on('calendario.fecha', 'enfunde.fecha')
                        ->whereBetween('calendario.codigo', [$inicio_fin_year->inicio, $inicio_fin_year->fin]);
                })
                ->groupBy('hacienda.id', 'hacienda.detalle', DB::raw("SUBSTRING(convert(varchar, calendario.codigo), 1, 3) + 1800"));

            if (!empty($idhacienda) && $idhacienda !== "null") {
                $sql = $sql->where('hacienda.id', $idhacienda);
            }

            $sql = $sql->get();
            //Se sabe que en el sisban solo hay datos de la hacienda Primo y Sofca (codigos 1 y 3), las demas haciendas que se agreguen, trabajan con datos del Bansis.

            $values = [];
            foreach ($sql as $item):
                $years = [];
                foreach ($options as $option) {
                    $total = 0;
                    if ($option == '2018' && ($item->id == 1 || $item->id == 3)) {
                        $total = $this->enfundeTotalHaciendaSisban($item->id, 2018, true, true, 1, 13, 1, 52)->first()->total;
                    } else if ($option == '2019' && ($item->id == 1 || $item->id == 3)) {
                        $total = $this->enfundeTotalHaciendaSisban($item->id, 2019, true, true, 1, 13, 1, 52)->first()->total;
                    } else if ($option == '2020' && ($item->id == 1 || $item->id == 3)) {
                        //Traer los datos de la semana 26, ya que este sistema empezo a capturar datos de primo en la 25 y sofca en la 26,
                        //asi que se toman los datos desde la semana 26 y se le añaden las semanas anteriores.
                        $total = $this->enfundeTotalHaciendaSisban($item->id, 2020, true, true, 1, 13, 1, 25)->first()->total;
                        $total += $item->total;
                    } else if ($option == $item->year) {
                        $total = $item->total;
                    }
                    array_push($years, intval($total));
                }
                array_push($values, [
                    'name' => $item->detalle,
                    'data' => $years
                ]);
            endforeach;

            /*array_push($this->out['series'], [
                'name' => $hacienda['detalle'],
                'data' => $values_primo
            ]);*/
            $this->out = $this->respuesta_json('success', 200, 'Consulta ejecutada');
            $this->out['series'] = $values;

            $this->out['categories'] = $options;

            return response()->json($this->out, 200);
        } catch (\Exception $ex) {
            $this->out = $this->respuesta_json('error', 500, 'Error en la solicitud!!!');
            $this->out['errors'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function dashboardEnfundeHectareas(Request $request)
    {
        try {
            $hacienda = $request->get('idhacienda');
            $periodo = $request->get('periodo');
            $semana = $request->get('semana');

            if (!empty($hacienda)) {
                $sql = DB::table('HAC_ENFUNDES AS enfunde')
                    ->select('seccion.id', DB::raw("RIGHT('000' + LTRIM(seccion.alias), 3) as alias"),
                        'seccion.has', DB::raw("sum(detalle.cant_pre + detalle.cant_fut) as total"),
                        DB::raw("(sum(detalle.cant_pre + detalle.cant_fut)/count(distinct calendario.semana))/seccion.has AS totalHasSemanal")
                    )
                    ->join('HAC_DET_ENFUNDES AS detalle', 'detalle.idenfunde', 'enfunde.id')
                    ->join('HAC_LOTSEC_LABEMPLEADO_DET AS laborSecDet', 'laborSecDet.id', 'detalle.idseccion')
                    ->join('HAC_LOTES_SECCION AS seccion', 'seccion.id', 'laborSecDet.idlote_sec')
                    ->join('HACIENDAS AS hacienda', 'hacienda.id', 'enfunde.idhacienda')
                    ->join('SIS_CALENDARIO_DOLE AS calendario', function ($query) {
                        $inicio_fin_year = $this->codigoCalendarioAnual(2020);
                        $query->on('calendario.fecha', 'enfunde.fecha')
                            ->whereBetween('calendario.codigo', [$inicio_fin_year->inicio, $inicio_fin_year->fin]);
                    })
                    ->where('hacienda.id', $hacienda)
                    ->groupBy('seccion.id', 'seccion.alias', 'seccion.has')
                    ->orderBy("seccion.has");

                if (!empty($periodo) && $periodo !== "null") {
                    $sql = $sql->where('calendario.periodo', $periodo);
                }

                if (!empty($semana) && $semana !== "null") {
                    $sql = $sql->where('calendario.semana', $semana);
                }

                $sql = $sql->get();

                $data = [];
                $dataId = [];

                foreach ($sql as $item) {
                    array_push($dataId, $item->id);
                    array_push($data, [round($item->has, 2), round($item->totalHasSemanal, 0)]);
                }

                $this->out = $this->respuesta_json('success', 200, 'Consulta ejecutada');
                $this->out['series'] = [
                    [
                        'name' => "Enfunde/Has.",
                        'data' => $data
                    ]
                ];
                $this->out['dataId'] = $dataId;

                return response()->json($this->out, 200);
            }
            throw new \Exception("No se ha recibido el codigo de la hacienda");
        } catch (\Exception $ex) {
            $this->out = $this->respuesta_json('error', 500, 'Error en la solicitud!!!');
            $this->out['errors'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function dashboardEnfundeSemanalLote(Request $request)
    {
        try {
            $idseccion = $request->get('idseccion');
            $periodo = $request->get('periodo');
            $semana = $request->get('semana');

            if (!empty($idseccion)) {
                $dataSeccion = LoteSeccion::select(
                    'id', DB::raw("RIGHT('000' + LTRIM(alias),3) AS alias"),
                    'has', 'variedad', 'tipo_suelo'
                )->where('id', $idseccion)->first();
                $sql = DB::table('HAC_ENFUNDES AS enfunde')
                    ->select('calendario.semana', DB::raw("sum(detalle.cant_pre + detalle.cant_fut) as total"))
                    ->join('HAC_DET_ENFUNDES AS detalle', 'detalle.idenfunde', 'enfunde.id')
                    ->join('HAC_LOTSEC_LABEMPLEADO_DET AS laborSecDet', 'laborSecDet.id', 'detalle.idseccion')
                    ->join('HAC_LOTES_SECCION AS seccion', 'seccion.id', 'laborSecDet.idlote_sec')
                    ->join('HACIENDAS AS hacienda', 'hacienda.id', 'enfunde.idhacienda')
                    ->join('SIS_CALENDARIO_DOLE AS calendario', function ($query) {
                        $inicio_fin_year = $this->codigoCalendarioAnual(2020);
                        $query->on('calendario.fecha', 'enfunde.fecha')
                            ->whereBetween('calendario.codigo', [$inicio_fin_year->inicio, $inicio_fin_year->fin]);
                    })
                    ->where('seccion.id', $idseccion)
                    ->groupBy('calendario.semana')
                    ->orderBy('calendario.semana');

                if (!empty($periodo) && $periodo !== "null") {
                    $sql = $sql->where('calendario.periodo', $periodo);
                }

                if (!empty($semana) && $semana !== "null") {
                    $sql = $sql->where('calendario.semana', $semana);
                }

                $sql = $sql->get();

                $series = [];
                $categories = [];

                foreach ($sql as $item) {
                    array_push($series, Round($item->total / $dataSeccion->has, 0));
                    array_push($categories, $item->semana);
                }

                $this->out = $this->respuesta_json('success', 200, 'Consulta ejecutada');
                $this->out['seccion'] = $dataSeccion;
                $this->out['series'] = [
                    'name' => 'Enfunde',
                    'data' => $series,
                ];
                $this->out['categories'] = $categories;

                return response()->json($this->out, 200);

            }
            throw new \Exception("No se ha recibido el codigo de la hacienda");
        } catch (\Exception $ex) {
            $this->out = $this->respuesta_json('error', 500, 'Error en la solicitud!!!');
            $this->out['errors'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function getLoterosLoteEnfunde(Request $request)
    {
        try {
            $idLote = $request->get('idlote');
            $periodo = $request->get('periodo');
            $semana = $request->get('semana');

            if (!empty($idLote)):
                $sql = DB::table('HAC_ENFUNDES AS enfunde')
                    ->select(
                        'empleado.id', 'empleado.nombres',
                        DB::raw("min(calendario.semana) SemanaInicio"),
                        DB::raw("max(calendario.semana) SemanaFin"),
                        DB::raw("count(distinct enfunde.id) semanasLaboradas"),
                        DB::raw("round(sum(laborSecDet.has)/count(distinct enfunde.id),2) HasProm"),
                        DB::raw("sum(detalle.cant_pre + detalle.cant_fut) total"),
                        DB::raw("round(sum(detalle.cant_pre + detalle.cant_fut)/(sum(laborSecDet.has)/count(distinct enfunde.id)),0) totalHas")
                    )
                    ->join('HAC_DET_ENFUNDES AS detalle', 'detalle.idenfunde', 'enfunde.id')
                    ->join('HAC_LOTSEC_LABEMPLEADO_DET AS laborSecDet', 'laborSecDet.id', 'detalle.idseccion')
                    ->join('HAC_LOTES_SECCION AS seccion', 'seccion.id', 'laborSecDet.idlote_sec')
                    ->join('HAC_LOTSEC_LABEMPLEADO AS laborSec', 'laborSec.id', 'laborSecDet.idcabecera')
                    ->join('HAC_EMPLEADOS AS empleado', 'empleado.id', 'laborSec.idempleado')
                    ->join('SIS_CALENDARIO_DOLE AS calendario', function ($query) {
                        $inicio_fin_year = $this->codigoCalendarioAnual(2020);
                        $query->on('calendario.fecha', 'enfunde.fecha')
                            ->whereBetween('calendario.codigo', [$inicio_fin_year->inicio, $inicio_fin_year->fin]);
                    })
                    ->where([
                        'seccion.id' => $idLote
                    ])
                    ->groupBy('empleado.id', 'empleado.nombres');

                if (!empty($periodo) && $periodo !== "null") {
                    $sql = $sql->where('calendario.periodo', $periodo);
                }

                if (!empty($semana) && $semana !== "null") {
                    $sql = $sql->where('calendario.semana', $semana);
                }

                $sql = $sql->get();

                $this->out = $this->respuesta_json('success', 200, 'Consulta ejecutada');
                $this->out['data'] = $sql->all();

                return response()->json($this->out, 200);
            else:
                throw new \Exception('No se ha recibido el codigo del lote');
            endif;
        } catch (\Exception $ex) {
            $this->out = $this->respuesta_json('error', 500, 'Error en la solicitud!!!');
            $this->out['errors'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function getLotesLoteroEnfunde(Request $request)
    {
        try {
            $idLotero = $request->get('idlotero');
            $periodo = $request->get('periodo');
            $semana = $request->get('semana');

            if (!empty($idLotero)):
                $sql = DB::table('HAC_ENFUNDES AS enfunde')
                    ->select(
                        'seccion.id', 'seccion.alias',
                        DB::raw("min(calendario.semana) SemanaInicio"),
                        DB::raw("max(calendario.semana) SemanaFin"),
                        DB::raw("count(distinct enfunde.id) semanasLaboradas"),
                        DB::raw("round(sum(laborSecDet.has)/count(distinct enfunde.id),2) HasProm"),
                        DB::raw("sum(detalle.cant_pre + detalle.cant_fut) total"),
                        DB::raw("round(sum(detalle.cant_pre + detalle.cant_fut)/(sum(laborSecDet.has)/count(distinct enfunde.id)),0) totalHas")
                    )
                    ->join('HAC_DET_ENFUNDES AS detalle', 'detalle.idenfunde', 'enfunde.id')
                    ->join('HAC_LOTSEC_LABEMPLEADO_DET AS laborSecDet', 'laborSecDet.id', 'detalle.idseccion')
                    ->join('HAC_LOTES_SECCION AS seccion', 'seccion.id', 'laborSecDet.idlote_sec')
                    ->join('HAC_LOTSEC_LABEMPLEADO AS laborSec', 'laborSec.id', 'laborSecDet.idcabecera')
                    ->join('HAC_EMPLEADOS AS empleado', 'empleado.id', 'laborSec.idempleado')
                    ->join('SIS_CALENDARIO_DOLE AS calendario', function ($query) {
                        $inicio_fin_year = $this->codigoCalendarioAnual(2020);
                        $query->on('calendario.fecha', 'enfunde.fecha')
                            ->whereBetween('calendario.codigo', [$inicio_fin_year->inicio, $inicio_fin_year->fin]);
                    })->where([
                        'empleado.id' => $idLotero
                    ])->groupBy('seccion.id', 'seccion.alias');

                if (!empty($periodo) && $periodo !== "null") {
                    $sql = $sql->where('calendario.periodo', $periodo);
                }

                if (!empty($semana) && $semana !== "null") {
                    $sql = $sql->where('calendario.semana', $semana);
                }

                $sql = $sql->get();

                $this->out = $this->respuesta_json('success', 200, 'Consulta ejecutada');
                $this->out['data'] = $sql->all();

                return response()->json($this->out, 200);
            else:
                throw new \Exception('No se ha recibido el codigo del lote');
            endif;
        } catch (\Exception $ex) {
            $this->out = $this->respuesta_json('error', 500, 'Error en la solicitud!!!');
            $this->out['errors'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function enfundeTotalHaciendaSisban($hacienda, $year, $periodal = false, $semanal = false, ...$data)
    {
        if ($hacienda == 1 || $hacienda == 3) {
            $tabla = $hacienda == 1 ? 'dbo.enfunde_primo' : 'dbo.enfunde_sofca';
            $sql = DB::connection('SISBAN')->table("$tabla AS enfunde")
                ->join('SISBAN.dbo.calendario_dole AS calendario', function ($sql) use ($year, $data, $semanal, $periodal) {
                    $inicio_fin_year = $this->codigoCalendarioAnual($year);

                    $sql->on('calendario.fecha', '=', 'enfunde.en_fecha')
                        ->whereBetween('calendario.idcalendar', [$inicio_fin_year->inicio, $inicio_fin_year->fin]);

                    $indice = [0, 1];
                    if (count($data) > 0) {

                        if ($periodal) {
                            $sql->whereBetween('calendario.periodo', [$data[$indice[0]], $data[$indice[1]]]);
                            if ($semanal) {
                                $indice[0] = 2;
                                $indice[1] = 3;
                            }
                        }

                        if ($semanal) {
                            $sql->whereBetween('calendario.semana', [$data[$indice[0]], $data[$indice[1]]]);
                        }
                    }
                });
            $sql->select(DB::raw("SUM(enfunde.en_cantpre + enfunde.en_cantfut) AS total"));

            return $sql;
        }
        return false;
    }

    public function codigoCalendarioAnual($year)
    {
        //Retorna el codigo de inicio y final del año
        return DB::connection('SISBAN')->table('calendario_dole AS calendario')
            ->select(DB::raw("min(idcalendar) inicio"), DB::raw("max(idcalendar) fin"))
            ->where(DB::raw("SUBSTRING(CONVERT(varchar, calendario.idcalendar), 1,3) + 1800"), $year)
            ->first();
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
