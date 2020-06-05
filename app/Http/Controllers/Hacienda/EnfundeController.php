<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Hacienda\Empleado;
use App\Models\Hacienda\Enfunde;
use App\Models\Hacienda\EnfundeDet;
use App\Models\Hacienda\InventarioEmpleado;
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
        $this->middleware('api.auth', ['except' => ['index', 'show', 'getEmpleados', 'getEnfundeDetalle']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        //
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
                        $materiales_usados = array();
                        foreach ($detalle as $item):
                            foreach ($item['presente'] as $material):
                                array_push($materiales_usados, $material['detalle']['material']['id']);
                            endforeach;
                            foreach ($item['futuro'] as $material):
                                array_push($materiales_usados, $material['detalle']['material']['id']);
                            endforeach;
                        endforeach;

                        $loteros_reelevos = array();
                        foreach ($detalle as $item):
                            foreach ($item['presente'] as $reelevo):
                                if ($reelevo['reelevo'])
                                    array_push($loteros_reelevos, $reelevo['reelevo']['id']);
                            endforeach;
                            foreach ($item['futuro'] as $reelevo):
                                if ($reelevo['reelevo'])
                                    array_push($loteros_reelevos, $reelevo['reelevo']['id']);
                            endforeach;
                        endforeach;

                        $materiales = array_map("unserialize", array_unique(array_map("serialize", $materiales_usados)));
                        $reelevos = array_map("unserialize", array_unique(array_map("serialize", $loteros_reelevos)));
                        $empleados = array_merge([$cabecera['empleado']['id']], $reelevos);

                        $this->setMaterialesUsados($empleados, $enfunde->idcalendar, $materiales);

                        foreach ($detalle as $item):
                            //return response()->json(['dato' => $item['presente'][2]], 200);
                            $this->detalleEnfunde($enfunde, $item['presente'], $cabecera['empleado']);
                            $this->detalleEnfunde($enfunde, $item['futuro'], $cabecera['empleado'], false);
                        endforeach;


                        DB::commit();
                        return response()->json($params, 200);
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
                $inventario['sld_final'] = intval($inventario['tot_egreso']);
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
                    $enfunde_detalle->where('idreelevo', $semana['reelevo']['id']);
                }

                $enfunde_detalle = $enfunde_detalle->first();

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
                }

                if ($presente) {
                    $enfunde_detalle->cant_pre = $semana['cantidad'];
                } else {
                    $enfunde_detalle->cant_fut = $semana['cantidad'];
                    $enfunde_detalle->cant_desb = $semana['desbunche'];
                }

                if ($semana['reelevo']) {
                    $enfunde_detalle->reelevo = 1;
                    $enfunde_detalle->idreelevo = $semana['reelevo']['id'];
                    $this->updateInventaryEmpleado($enfunde->idcalendar, $semana['detalle']['material'], $semana['reelevo'], $semana['cantidad']);
                } else {
                    $this->updateInventaryEmpleado($enfunde->idcalendar, $semana['detalle']['material'], $empleado, $semana['cantidad']);
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
                $inventario->sld_final = +$inventario->tot_egreso - $inventario->tot_devolucion;
                $inventario->updated_at = Carbon::now()->format(config('constants.format_date'));
                $inventario->save();
            }
            return true;
        } catch (\Exception $ex) {
            return false;
        }
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
                $enfundeDetalle = Enfunde::select('id', 'idcalendar', 'fecha', 'presente', 'futuro', 'cerrado', 'estado')
                    ->where([
                        'idcalendar' => $idcalendar,
                        'idhacienda' => $idhacienda
                    ])->with(['detalle' => function ($query) use ($idseccion) {
                        $query->select('id', 'idenfunde', 'idmaterial', 'idseccion', 'cant_pre', 'cant_fut', 'cant_desb', 'reelevo', 'idreelevo');
                        $query->where(['idseccion' => $idseccion]);
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

                $presente = array();
                $futuro = array();
                $totalP = 0;
                $totalF = 0;
                $totalD = 0;

                foreach ($enfundeDetalle->detalle as $detalle) {
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
                        $enfunde->cantidad = intval($detalle['cant_pre']);
                        $totalP += $enfunde->cantidad;
                        $enfunde->desbunche = 0;
                        array_push($presente, $enfunde);
                    }

                    if (intval($detalle['cant_fut']) > 0) {
                        $enfunde->cantidad = intval($detalle['cant_fut']);
                        $enfunde->desbunche = intval($detalle['cant_desb']);
                        $totalF += $enfunde->cantidad;
                        $totalD += $enfunde->desbunche;
                        array_push($futuro, $enfunde);
                    }

                }

                return response()->json([
                    'presente' => $presente,
                    'futuro' => $futuro,
                    'totalP' => $totalP,
                    'totalF' => $totalF,
                    'totalD' => $totalD,
                ], 200);
            }
        } catch (\Exception $ex) {
            return response()->json([], 200);
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


    public function destroy($id, Request $request)
    {
        try {
            $futuro = $request->get('futuro');

            $seccion_enfundada = EnfundeDet::where(['id' => $id])
                ->with(['seccion' => function ($query) {
                    $query->select('id', 'idcabecera', 'idlote_Sec');
                    $query->with(['cabSeccionLabor']);
                }])
                ->with(['enfunde' => function ($query) {
                    $query->select('id', 'idhacienda', 'idcalendar');
                }])
                ->first();

            if (is_object($seccion_enfundada)) {

                $calendario = $seccion_enfundada->enfunde->idcalendar;
                $idmaterial = $seccion_enfundada->idmaterial;
                $idempleado = $seccion_enfundada->seccion->cabSeccionLabor->idempleado;

                if ($seccion_enfundada->reelevo == 1) {
                    $idempleado = $seccion_enfundada->idreelevo;
                }

                $inventario_empleado = InventarioEmpleado::where([
                    'idempleado' => $idempleado,
                    'idcalendar' => $calendario,
                    'idmaterial' => $idmaterial
                ])->first();

                if ($futuro) {
                    $inventario_empleado->tot_devolucion -= $seccion_enfundada->cant_fut;
                }
                //$inventario_empleado->tot_devolucion -= $seccion_enfundada
                $inventario_empleado->save();
                return response()->json($seccion_enfundada, 200);
            }

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
                    'material' => 'required',
                    'seccion' => 'required',
                    'hacienda' => 'required',
                    'calendario' => 'required',
                    'cantidad' => 'required',
                    'presente' => 'required',
                    'futuro' => 'required'
                ]);

                if (!$validacion->fails()) {
                    $enfunde = Enfunde::where([
                        'idcalendar' => $params->calendario,
                        'hacienda' => $params->hacienda
                    ])->with(['detalle' => function ($query) {
                        $query->select();
                    }]);
                }

                $this->out['errors'] = $validacion->errors()->all();
                throw new \Exception('No se han recibido los datos completos');
            }

        } catch (\Exception $ex) {

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
