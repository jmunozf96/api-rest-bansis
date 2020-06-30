<?php

namespace App\Http\Controllers\Bodega;

use App\Http\Controllers\Controller;
use App\Models\Bodega\EgresoBodega;
use App\Models\Bodega\EgresoBodegaDetalle;
use App\Models\Bodega\Material;
use App\Models\Hacienda\Empleado;
use App\Models\Hacienda\InventarioEmpleado;
use App\Models\Sistema\Calendario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EgresoBodegaController extends Controller
{
    protected $out;
    protected $detalle;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show', 'getTransaccion', 'showTransferencia']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index(Request $request)
    {
        try {
            $hacienda = $request->get('hacienda');
            $labor = $request->get('labor');
            $periodo = $request->get('periodo');
            $semana = $request->get('semana');
            $empleado = $request->get('empleado');

            $egresos = EgresoBodega::select('id', 'codigo', 'idcalendario', 'periodo', 'semana', 'idempleado', 'updated_at', 'estado');

            if (!empty($periodo) && isset($periodo))
                $egresos = $egresos->where('periodo', $periodo);

            if (!empty($semana) && isset($semana))
                $egresos = $egresos->where('semana', $semana);

            if (!empty($empleado) && isset($empleado))
                $egresos = $egresos->where('idempleado', $empleado);

            $egresos = $egresos->whereHas('egresoEmpleado', function ($query) use ($hacienda, $labor) {
                if (!empty($hacienda) && isset($hacienda))
                    $query->where('idhacienda', $hacienda);

                if (!empty($labor) && isset($labor))
                    $query->where('idlabor', $labor);

            })->with(['egresoEmpleado' => function ($query) {
                $query->select('id', 'idhacienda', 'nombres', 'idlabor');
                $query->with(['labor' => function ($query) {
                    $query->select('id', 'descripcion');
                }]);
            }])
                ->orderBy('updated_at', 'DESC')
                ->paginate(7);

            if (!is_null($egresos) && !empty($egresos) && count($egresos) > 0) {
                $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
                $this->out['dataArray'] = $egresos;
                return response()->json($this->out, $this->out['code']);
            } else {
                throw new \Exception('Lo sentimos!, No se han encontrado datos.');
            }
        } catch (\Exception $exception) {
            $this->out['message'] = $exception->getMessage();
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function store(Request $request)
    {
        try {
            $json = $request->input('json');
            $params = json_decode($json);
            $params_array = json_decode($json, true);
            if (!empty($params) && isset($params)) {

                DB::beginTransaction();

                $validacion = Validator::make($params_array, [
                    'cabecera.empleado.id' => 'required',
                    'cabecera.hacienda' => 'required',
                    'detalle' => 'required|array'
                ], [
                    'cabecera.empleado.id.required' => "El empleado es necesario",
                    'cabecera.hacienda.required' => "Es necesario seleccionar una hacienda",
                    'detalle.required' => "No se ha seleccionado ningun material"
                ]);

                if (!$validacion->fails()) {
                    $cabecera = $params_array['cabecera'];
                    $detalle = $params_array['detalle'];

                    $timestamp = strtotime(str_replace('/', '-', $cabecera['fecha']));
                    $cabecera['fecha'] = date(config('constants.date'), $timestamp);

                    $calendario = Calendario::where('fecha', $cabecera['fecha'])->first();

                    //Para el inventario
                    $cabecera['idcalendario'] = $calendario->codigo;

                    if (is_object($calendario)) {
                        //Se registra la cabecera
                        $existe_egreso = EgresoBodega::where([
                            'idcalendario' => $calendario->codigo,
                            'periodo' => $calendario->periodo,
                            'semana' => $calendario->semana,
                            'idempleado' => $cabecera['empleado']['id']
                        ])->first();

                        if (empty($existe_egreso) || is_null($existe_egreso) || !is_object($existe_egreso)) {
                            $egreso = new EgresoBodega();
                            $egreso->codigo = $this->codigoTransaccion(intval($cabecera['hacienda']));
                            $egreso->fecha = $cabecera['fecha'];
                            $egreso->idempleado = $cabecera['empleado']['id'];
                            $egreso->idcalendario = $calendario->codigo;
                            $egreso->periodo = $calendario->periodo;
                            $egreso->semana = $calendario->semana;
                            $egreso->created_at = Carbon::now()->format(config('constants.format_date'));
                            $egreso->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $egreso->save();

                            $cabecera['id'] = $egreso->id;
                            foreach ($detalle as $item) {
                                $this->storeDetalleTransaccion($item, $cabecera);
                            }
                            $mensaje = 'Se registro correctamente la transaccion #' . $egreso->codigo;
                            $this->out['codigo_transaccion'] = $egreso->codigo;
                        } else {
                            $cabecera['id'] = $existe_egreso->id;
                            foreach ($detalle as $item) {
                                $this->storeDetalleTransaccion($item, $cabecera);
                            }
                            $existe_egreso->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $existe_egreso->save();
                            $mensaje = 'Se actualizo correctamente la transaccion #' . $existe_egreso->codigo;
                        }
                        $this->out = $this->respuesta_json('success', 200, $mensaje . ', correspondiente al empleado: ' . $cabecera['empleado']['nombres']);

                        DB::commit();
                        return response()->json($this->out, 200);
                    }
                    throw new \Exception('La fecha no se encuentra en el calendario', 500);
                } else {
                    $this->out['code'] = 404;
                    $this->out['errors'] = $validacion->errors()->all();
                    $this->out['message'] = 'No se han recibido datos para procesar la transaccion!';
                    return response()->json($this->out, 500);
                }

            }
            throw new \Exception('No se han recibido parametros', 500);
        } catch (\Exception $exception) {
            DB::rollBack();
            $out['code'] = $exception->getCode();
            $out['errors'] = $exception->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function storeDetalleTransaccion($detalle, $cabecera)
    {
        try {

            $timestamp = strtotime(str_replace('/', '-', $detalle['time']));
            $detalle['time'] = date(config('constants.date'), $timestamp);

            $existe_detalle = EgresoBodegaDetalle::where([
                'idegreso' => $cabecera['id'],
                'idmaterial' => $detalle['idmaterial'],
                'fecha_salida' => $detalle['time']
            ])->first();

            if (is_object($existe_detalle)) {
                if (!$this->testMovimientosDetalle($existe_detalle)) {
                    if ($this->storeInventario($cabecera, $detalle, true, $existe_detalle->cantidad)) {
                        if (intval($existe_detalle->cantidad) !== intval($detalle['cantidad'])) {
                            $existe_detalle->cantidad = $detalle['cantidad'];
                            $existe_detalle->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $existe_detalle->update();
                        }
                    }
                }
            } else {
                if ($this->storeInventario($cabecera, $detalle, false)) {
                    $egreso_detalle = new EgresoBodegaDetalle();
                    $egreso_detalle->idegreso = $cabecera['id'];
                    $egreso_detalle->idmaterial = $detalle['idmaterial'];
                    //Añadir el movimiento que se esta realizando y llamar a funcion que va a identificar el tipo
                    //de movimiento y ejecutara una accion respectiva
                    $egreso_detalle->fecha_salida = $detalle['time'];
                    $egreso_detalle->cantidad = $detalle['cantidad'];
                    $egreso_detalle->created_at = Carbon::now()->format(config('constants.format_date'));
                    $egreso_detalle->updated_at = Carbon::now()->format(config('constants.format_date'));
                    $egreso_detalle->save();
                }
            }
            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function storeInventario($cabecera, $detalle, $edit = false, $cantidad_old = 0)
    {
        try {
            //Guardar el inventario
            $egreso_inventario = new \stdClass();
            $egreso_inventario->idcalendario = $cabecera['idcalendario'];
            $egreso_inventario->idempleado = $cabecera['empleado']['id'];
            $egreso_inventario->idhacienda = $cabecera['hacienda'];
            $egreso_inventario->idmaterial = $detalle['idmaterial'];
            $egreso_inventario->cantidad = $detalle['cantidad'];
            $egreso_inventario->edicion = $edit;
            $egreso_inventario->cantidad_old = $cantidad_old;
            return $this->saveInventario($egreso_inventario);
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function saveInventario($egreso)
    {
        try {
            if (is_object($egreso)) {
                $inventario = InventarioEmpleado::where([
                    'idcalendar' => $egreso->idcalendario,
                    'idempleado' => $egreso->idempleado,
                    'idmaterial' => $egreso->idmaterial
                ])->first();

                $material_stock = Material::where(['id' => $egreso->idmaterial])->first();

                if (!is_object($inventario)) {
                    $inventario = new InventarioEmpleado();
                    $inventario->codigo = $this->codigoTransaccionInventario($egreso->idhacienda);
                    $inventario->idcalendar = $egreso->idcalendario;
                    $inventario->idempleado = $egreso->idempleado;
                    $inventario->idmaterial = $egreso->idmaterial;
                    $inventario->sld_inicial = 0;
                    $inventario->created_at = Carbon::now()->format(config('constants.format_date'));
                    $inventario->tot_egreso = intval($egreso->cantidad);
                    $material_stock->stock = intval($material_stock->stock) - intval($egreso->cantidad);
                } else {
                    if (!$egreso->edicion) {
                        $inventario->tot_egreso += intval($egreso->cantidad);
                        $material_stock->stock = intval($material_stock->stock) - intval($egreso->cantidad);
                    } else {
                        $inventario->tot_egreso = (intval($inventario->tot_egreso) - intval($egreso->cantidad_old)) + intval($egreso->cantidad);
                        $material_stock->stock = (intval($material_stock->stock) + intval($egreso->cantidad_old)) - intval($egreso->cantidad);
                    }
                }

                $inventario->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);
                if ($inventario->sld_final >= 0) {
                    $inventario->updated_at = Carbon::now()->format(config('constants.format_date'));
                    $inventario->save();
                    $material_stock->save();
                    return true;
                } else {
                    return false;
                }

            }
            return false;
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function codigoTransaccionInventario($hacienda = 1)
    {
        $transacciones = InventarioEmpleado::select('codigo')->get();
        $path = $hacienda == 1 ? 'PRI-INV' : 'SFC-INV';
        $codigo = $path . '-' . str_pad(count($transacciones) + 1, 6, "0", STR_PAD_LEFT);;
        return $codigo;
    }

    public function getTransaccion(Request $request)
    {
        try {
            $empleado = $request->get('empleado');
            $fecha = $request->get('fecha');
            if (!empty($empleado) && !empty($fecha)) {
                $timestamp = strtotime(str_replace('/', '-', $fecha));
                $calendario = Calendario::where('fecha', date(config('constants.date'), $timestamp))->first();
                if (is_object($calendario)) {
                    $egreso = EgresoBodega::where([
                        'periodo' => $calendario->periodo,
                        'semana' => $calendario->semana,
                        'idempleado' => $empleado
                    ])
                        ->with('egresoEmpleado')
                        ->with(['egresoDetalle' => function ($query) {
                            $query->with('materialdetalle');
                        }])
                        ->first();
                    if(is_object($egreso)){
                        $this->out = $this->respuesta_json('success', 200, 'Datos encontrados!');
                        $this->out['egreso'] = $egreso;
                        return response()->json($this->out, $this->out['code']);
                    }
                    throw new \Exception('No se encontraron despachos para este empleado');
                }
            }
            throw new \Exception('No se encontraron datos para esta fecha');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 200);
        }
    }

    public function showTransferencia(Request $request)
    {
        try {
            $id = $request->get('id');
            $detalle = EgresoBodegaDetalle::where(['id' => $id])->first();
            if (is_object($detalle)) {
                $detalle = EgresoBodegaDetalle::where('id', $id)
                    ->with(['destino' => function ($query) {
                        $query->with(['materialdetalle' => function ($query) {
                            $query->select('id', 'descripcion');
                        }]);
                        $query->select('id', 'idegreso', 'idmaterial', 'movimiento', 'fecha_salida', 'cantidad', 'id_origen');
                        $query->with(['cabeceraEgreso' => function ($query) {
                            $query->select('id', 'idcalendario', 'idempleado');
                            $query->with(['egresoEmpleado' => function ($query) {
                                $query->select('id', 'nombres');
                            }]);
                        }]);
                    }])
                    ->with(['compartido' => function ($query) {
                        $query->with(['materialdetalle' => function ($query) {
                            $query->select('id', 'descripcion');
                        }]);
                        $query->select('id', 'idegreso', 'idmaterial', 'movimiento', 'fecha_salida', 'cantidad');
                        $query->with(['cabeceraEgreso' => function ($query) {
                            $query->select('id', 'idcalendario', 'idempleado');
                            $query->with(['egresoEmpleado' => function ($query) {
                                $query->select('id', 'nombres');
                            }]);
                        }]);
                    }])->first();

                return response()->json($detalle, 200);
            }
            throw new \Exception('No se encontraron datos.');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function preparedestroyTransferencia(Request $request)
    {
        try {
            $id = $request->get('id');
            $detalle = EgresoBodegaDetalle::where('id', $id)->first();

            DB::beginTransaction();
            if ($this->destroyTransferencia($detalle, true)) {
                $detalle_origen = EgresoBodegaDetalle::where('id_origen', $detalle->id)->first();
                $detalle->delete();

                $cabecera = EgresoBodega::where('id', $detalle->idegreso)->first();
                $detalles = EgresoBodegaDetalle::where('idegreso', $cabecera->id)->get();
                if (count($detalles) == 0) {
                    $cabecera->delete();
                }

                if ($this->destroyTransferencia($detalle_origen, true)) {
                    $detalle_origen->delete();

                    $cabecera = EgresoBodega::where('id', $detalle_origen->idegreso)->first();
                    $detalles = EgresoBodegaDetalle::where('idegreso', $cabecera->id)->get();
                    if (count($detalles) == 0) {
                        $cabecera->delete();
                    }

                    $this->out = $this->respuesta_json('success', 200, 'Movimiento eliminado con exito!');
                    DB::commit();
                    return response()->json($this->out, 200);
                }
            }
            throw new \Exception('No se pudo eliminar el movimiento');
        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }

    }

    public function destroyTransferencia($detalle, $solicita = true)
    {
        try {
            if (is_object($detalle)) {
                $cabecera = EgresoBodega::where('id', $detalle->idegreso)->first();
                //Restar a mi inventario
                $inventario = InventarioEmpleado::where([
                    'idempleado' => $cabecera->idempleado,
                    'idcalendar' => $cabecera->idcalendario,
                    'idmaterial' => $detalle->idmaterial
                ])->first();

                if (!is_object($inventario)) {
                    $emplado = Empleado::where('id', $cabecera->idempleado)->first();
                    $inventario = new InventarioEmpleado();
                    $inventario->codigo = $this->codigoTransaccionInventario($emplado->idhacienda);
                    $inventario->idcalendar = $cabecera->idcalendario;
                    $inventario->idempleado = $emplado->id;
                    $inventario->idmaterial = $detalle->idmaterial;
                    $inventario->sld_inicial = 0;
                    $inventario->created_at = Carbon::now()->format(config('constants.format_date'));
                    $inventario->tot_devolucion = 0;
                }

                if ($solicita) {
                    $inventario->tot_egreso -= $detalle->cantidad;
                } else {
                    $inventario->tot_egreso += $detalle->cantidad;
                }

                $inventario->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);

                if ($inventario->sld_final < 0) {
                    return false;
                }

                $inventario->updated_at = Carbon::now()->format(config('constants.format_date'));
                $inventario->save();

                if ($inventario->sld_final == 0) {
                    $inventario->delete();
                }
            }
            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function codigoTransaccion($hacienda = 1)
    {
        $transacciones = EgresoBodega::select('codigo')->get();
        $path = $hacienda == 1 ? 'PRI' : 'SFC';
        $codigo = $path . '-' . str_pad(count($transacciones) + 1, 10, "0", STR_PAD_LEFT);;
        return $codigo;
    }

    public function saldotransfer(Request $request)
    {
        try {
            $json = $request->input('json');
            $params = json_decode($json);
            $params_array = json_decode($json, true);
            if (is_object($params) && !empty($params)) {
                $validacion = Validator::make($params_array, [
                    'emp_solicitado' => 'required',
                    'emp_recibe' => 'required',
                    'id_inventario_tomado' => 'required',
                    'cantidad' => 'required'
                ], [
                    'emp_solicitado.required' => 'El empleado al que se le solicita el traspaso es requerido',
                    'emp_recibe.required' => 'El empleado que recibe el traspaso es requerido',
                    'id_inventario_tomado.required' => 'No se ha tomado ningun inventario',
                    'cantidad.required' => 'No hay cantidad',
                ]);

                if (!$validacion->fails()) {
                    DB::beginTransaction();
                    $timestamp = strtotime(str_replace('/', '-', $params_array['time']));
                    $params_array['time'] = date(config('constants.date'), $timestamp);
                    //Transferencia
                    //Buscamos el dato de donde se va a transferir
                    $inventario = InventarioEmpleado::where('id', $params_array['id_inventario_tomado'])->first();

                    if (is_object($inventario)) {
                        $inventario->tot_egreso -= $params_array['cantidad'];
                        $inventario->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);
                        $inventario->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $inventario->save();

                    }

                    $calendario = Calendario::where('fecha', $params_array['time'])->first();

                    $egreso = null;
                    $movimiento = null;
                    $bandera = true;
                    do {
                        //Generamos el movimiento de transferencia (-)
                        $egreso = EgresoBodega::where([
                            'idempleado' => $params_array['emp_solicitado']['id'],
                            'idcalendario' => $inventario->idcalendar
                        ])->first();

                        if (is_object($egreso)) {
                            $movimiento = new EgresoBodegaDetalle();
                            $movimiento->idegreso = $egreso->id;
                            $movimiento->idmaterial = $inventario->idmaterial;
                            $movimiento->movimiento = 'TRASP-SAL';
                            $movimiento->fecha_salida = $params_array['time'];
                            $movimiento->cantidad = -$params_array['cantidad'];
                            $movimiento->created_at = Carbon::now()->format(config('constants.format_date'));
                            $movimiento->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $movimiento->save();
                            $bandera = false;
                            //Si ya existe un movimiento con esa misma fecha editarlo
                        } else {
                            $egreso = new EgresoBodega();
                            $egreso->codigo = $this->codigoTransaccion($params_array['emp_solicitado']['idhacienda']);
                            $egreso->idcalendario = $calendario->codigo;
                            $egreso->periodo = $calendario->periodo;
                            $egreso->semana = $calendario->semana;
                            $egreso->idempleado = $params_array['emp_solicitado']['id'];
                            $egreso->fecha = $params_array['time'];
                            $egreso->created_at = Carbon::now()->format(config('constants.format_date'));
                            $egreso->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $egreso->save();
                        }
                    } while (is_null($egreso) || $bandera);


                    if ($inventario->tot_egreso <= 0) {
                        $inventario->delete();
                    }

                    //Pasamos el saldo al inventario correspondiente como nuevo item,
                    $existe_inventario_transferir = InventarioEmpleado::where([
                        'idcalendar' => $calendario->codigo,
                        'idempleado' => $params_array['emp_recibe']['id'],
                        'idmaterial' => $inventario->idmaterial
                    ])->first();

                    if (!is_object($existe_inventario_transferir) && empty($existe_inventario_transferir)) {
                        $inventario_traspaso = new InventarioEmpleado();
                        $inventario_traspaso->codigo = $this->codigoTransaccionInventario($params_array['hacienda']);
                        $inventario_traspaso->idcalendar = $calendario->codigo;
                        $inventario_traspaso->idempleado = $params_array['emp_recibe']['id'];
                        $inventario_traspaso->idmaterial = $inventario->idmaterial;
                        $inventario_traspaso->sld_inicial = 0;
                        $inventario_traspaso->tot_egreso = $params_array['cantidad'];
                        $inventario_traspaso->tot_devolucion = 0;
                        $inventario_traspaso->sld_final = (intval($inventario_traspaso->sld_inicial) + intval($inventario_traspaso->tot_egreso)) - intval($inventario_traspaso->tot_devolucion);
                        $inventario_traspaso->created_at = Carbon::now()->format(config('constants.format_date'));
                        $inventario_traspaso->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $inventario_traspaso->save();
                    } else {
                        $existe_inventario_transferir->tot_egreso += $params_array['cantidad'];
                        $existe_inventario_transferir->sld_final = (intval($existe_inventario_transferir->sld_inicial) + intval($existe_inventario_transferir->tot_egreso)) - intval($existe_inventario_transferir->tot_devolucion);
                        $existe_inventario_transferir->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $existe_inventario_transferir->save();
                    }
                    //si la fecha ya esta registrada con ese detalle se lo edita, caso contrario va como nuevo
                    //Generamos el movimiento de transferencia (+)
                    $egreso_recibido = EgresoBodega::where([
                        'idempleado' => $params_array['emp_recibe']['id'],
                        'idcalendario' => $calendario->codigo
                    ])->first();

                    $detalle = new EgresoBodegaDetalle();

                    if (!is_object($egreso_recibido)) {
                        //crear el movimiento con cabecera y detalle completo
                        $cabecera = new EgresoBodega();
                        $cabecera->codigo = $this->codigoTransaccion($params_array['hacienda']);
                        $cabecera->idcalendario = $inventario->idcalendar;
                        $cabecera->periodo = $calendario->periodo;
                        $cabecera->semana = $calendario->semana;
                        $cabecera->fecha = $params_array['time'];
                        $cabecera->idempleado = $params_array['emp_recibe']['id'];
                        $cabecera->created_at = Carbon::now()->format(config('constants.format_date'));
                        $cabecera->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $cabecera->save();
                        $detalle->idegreso = $cabecera->id;
                    } else {
                        $detalle->idegreso = $egreso_recibido->id;
                    }

                    $detalle->idmaterial = $inventario->idmaterial;
                    $detalle->movimiento = 'TRASP-SAL';
                    $detalle->fecha_salida = $params_array['time'];
                    $detalle->cantidad = $params_array['cantidad'];
                    $detalle->id_origen = $movimiento->id;
                    $detalle->created_at = Carbon::now()->format(config('constants.format_date'));
                    $detalle->updated_at = Carbon::now()->format(config('constants.format_date'));
                    $detalle->save();

                    DB::commit();
                    $this->out = $this->respuesta_json('success', 200, 'Movimiento registrado con exito.');
                    return response()->json($this->out, 200);

                } else {
                    $this->out['code'] = 400;
                    $this->out['errors'] = $validacion->errors()->all();
                    throw new \Exception('No se han recibido todos los parametros solicitados.');
                }
            }
            $this->out['code'] = 500;
            throw new \Exception('No se han recibido parametros');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function movimientos($descripcion)
    {
        switch ($descripcion) {
            case 'TRASP-SAL':
                //Movimiento de saldos por empleados
                //Empleado A -> Empleado B
                //Registrar movimiento para bajar saldo -> Registrar movimiento para aumentar el saldo
                break;
        }
    }

    public function show($id)
    {
        try {
            $egreso = EgresoBodega::select('id', 'idcalendario', 'periodo', 'semana', 'idempleado', 'fecha', 'estado')
                ->where('id', $id)
                ->with(['egresoEmpleado' => function ($query) {
                    $query->select('id', 'idhacienda', 'idlabor', 'cedula', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'nombres as descripcion', 'nombres');
                    $query->with(['labor' => function ($query) {
                        $query->select('id', 'descripcion');
                    }]);
                }])
                ->first();
            if (is_object($egreso) && !empty($egreso)) {
                $this->out['code'] = 200;
                $this->out['message'] = 'Datos encontrados con exito!';
                $this->out['egreso'] = $egreso;
                return response()->json($this->out, $this->out['code']);
            }
            throw new \Exception('No se encontraron datos para esta transaccion');
        } catch (\Exception $ex) {
            $this->out['code'] = 500;
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function destroy($id)
    {
        try {
            $eliminar_cabecera = false;
            $detalle_egreso = EgresoBodegaDetalle::where(['id' => $id, 'movimiento' => 'EGRE-ART'])->first();
            if (is_object($detalle_egreso)) {
                DB::beginTransaction();

                $status = false;

                //Si este material ha tenido movimientos
                if ($this->testMovimientosDetalle($detalle_egreso))
                    throw new \Exception('No se puede procesar esta solicitud, se ha transferido un saldo de este material.');

                $status = $detalle_egreso->delete();

                $egresos = EgresoBodegaDetalle::where('idegreso', $detalle_egreso->idegreso)->get();
                if (count($egresos) == 0)
                    $eliminar_cabecera = true;

                //Inventario
                $egreso = EgresoBodega::where(['id' => $detalle_egreso->idegreso])->first();

                $inventario = InventarioEmpleado::where([
                    'idcalendar' => $egreso->idcalendario,
                    'idempleado' => $egreso->idempleado,
                    'idmaterial' => $detalle_egreso->idmaterial
                ])->first();
                $inventario->tot_egreso = intval($inventario->tot_egreso) - intval($detalle_egreso->cantidad);
                $inventario->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);

                if ($inventario->sld_final < 0) {
                    throw new \Exception('No se puede eliminar este movimiento');
                }

                $status = $inventario->save();


                if (intval($inventario->sld_final) === 0)
                    $status = $inventario->delete();

                if (!$status) {
                    throw new \Exception('No se puede eliminar el registro.');
                }

                if ($eliminar_cabecera) {
                    $status = $egreso->delete();
                    if (!$status) {
                        throw new \Exception('No se puede eliminar el registro.');
                    }
                }

                $material_stock = Material::where(['id' => $detalle_egreso->idmaterial])->first();
                $material_stock->stock += floatval($detalle_egreso->cantidad);
                $material_stock->save();

                if ($status) {
                    DB::commit();
                    $this->out = $this->respuesta_json('success', 200, 'Movimiento se ha eliminado correctamente, inventario actualizado.');
                    return response()->json($this->out, $this->out['code']);
                }

            }
            throw new \Exception('No se encontro el registro con este Id');
        } catch (\Exception $ex) {
            $this->out['code'] = 500;
            $this->out['message'] = $ex->getMessage();
            $this->out['error_message'] = $ex->getMessage();
            DB::rollBack();
            return response()->json($this->out, $this->out['code']);
        }
    }

    public function testMovimientosDetalle($detalle_egreso)
    {
        $cantidad_transferidas = 0;
        $egresos = EgresoBodegaDetalle::where('idegreso', $detalle_egreso->idegreso)->get();
        foreach ($egresos as $egreso) {
            if ($egreso->movimiento == 'TRASP-SAL'
                && $egreso->idmaterial == $detalle_egreso->idmaterial
                && $egreso->id_origen == null) {
                $cantidad_transferidas += intval($egreso->cantidad);
            }
        }

        if ($cantidad_transferidas != 0) {
            return true;
        }

        return false;
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
