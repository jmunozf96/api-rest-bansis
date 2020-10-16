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

            $egresos = EgresoBodega::select('id', 'codigo', 'idcalendario',
                'periodo', 'semana', 'idempleado',
                'updated_at', 'estado');

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
            }])->orderBy('idcalendario', 'desc')
                ->orderBy('estado', 'DESC')
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
                    'detalle' => 'required|array',
                    'devolucion' => 'required'
                ], [
                    'cabecera.empleado.id.required' => "El empleado es necesario",
                    'cabecera.hacienda.required' => "Es necesario seleccionar una hacienda",
                    'detalle.required' => "No se ha seleccionado ningun material"
                ]);

                if (!$validacion->fails()) {
                    $cabecera = $params_array['cabecera'];
                    $detalle = $params_array['detalle'];
                    $devolucion = boolval($params_array['devolucion']);

                    $timestamp = strtotime(str_replace('/', '-', $cabecera['fecha']));
                    $cabecera['fecha'] = date(config('constants.date'), $timestamp);

                    $calendario = Calendario::where('fecha', $cabecera['fecha'])->first();

                    //Para el inventario
                    $cabecera['idcalendario'] = $calendario->codigo;

                    if (is_object($calendario)) {
                        //Se registra la cabecera
                        $egreso = EgresoBodega::where([
                            'idcalendario' => $calendario->codigo,
                            'periodo' => $calendario->periodo,
                            'semana' => $calendario->semana,
                            'idempleado' => $cabecera['empleado']['id'],
                            'estado' => true
                        ])->first();

                        if (empty($egreso) || is_null($egreso) || !is_object($egreso)) {
                            $egreso = new EgresoBodega();
                            $egreso->codigo = $this->codigoTransaccion(intval($cabecera['hacienda']));
                            $egreso->fecha = $cabecera['fecha'];
                            $egreso->idempleado = $cabecera['empleado']['id'];
                            $egreso->idcalendario = $calendario->codigo;
                            $egreso->periodo = $calendario->periodo;
                            $egreso->semana = $calendario->semana;
                            $egreso->created_at = Carbon::now()->format(config('constants.format_date'));

                            if (!$devolucion) {
                                $egreso->estado = false;
                            }

                            $mensaje = 'Se registro correctamente la transaccion #' . $egreso->codigo;
                            $this->out['codigo_transaccion'] = $egreso->codigo;
                        } else {
                            $mensaje = 'Se actualizo correctamente la transaccion #' . $egreso->codigo;
                        }

                        $egreso->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $egreso->save();

                        $cabecera['id'] = $egreso->id;
                        foreach ($detalle as $item) {
                            if (isset($item['transfer'])) {
                                if (!$this->saldoAcreditadoDebitado((object)$item['dataTransfer'])) {
                                    throw new \Exception('No se pudo procesar esta transaccion, hay un problema con la transferencia de saldo del empleado ' . $item->dataTransfer->emp_solicitado->descripcion);
                                }
                            } else {
                                $this->storeDetalleTransaccion($item, $cabecera, $devolucion);
                            }
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

    public function storeDetalleTransaccion($detalle, $cabecera, $devolucion = true)
    {
        try {

            $timestamp = strtotime(str_replace('/', '-', $detalle['time']));
            $detalle['time'] = date(config('constants.date'), $timestamp);

            $existe_detalle = EgresoBodegaDetalle::where([
                'idegreso' => $cabecera['id'],
                'idmaterial' => $detalle['idmaterial'],
                'fecha_salida' => $detalle['time'],
                'estado' => true
            ])->first();

            if (is_object($existe_detalle)) {
                if (!$this->testMovimientosDetalle($existe_detalle)) {
                    if ($this->storeInventario($cabecera, $detalle, true, $existe_detalle->cantidad, $devolucion)) {
                        if (intval($existe_detalle->cantidad) !== intval($detalle['cantidad'])) {
                            $existe_detalle->cantidad = $detalle['cantidad'];
                            $existe_detalle->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $existe_detalle->update();
                        }
                    }
                }
            } else {
                if ($this->storeInventario($cabecera, $detalle, false, 0, $devolucion)) {
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

    public function storeInventario($cabecera, $detalle, $edit = false, $cantidad_old = 0, $devolucion = true)
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
            $egreso_inventario->estado = $devolucion;
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
                    'idmaterial' => $egreso->idmaterial,
                    'estado' => $egreso->estado
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
                    $inventario->estado = $egreso->estado;
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
                    if (is_object($egreso)) {
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
                    ->with(['debito_transfer' => function ($query) {
                        $query->with(['materialdetalle' => function ($query) {
                            $query->select('id', 'descripcion');
                        }]);
                        $query->select('id', 'idegreso', 'idmaterial', 'movimiento', 'fecha_salida', 'cantidad', 'id_origen', 'debito');
                        $query->with(['cabeceraEgreso' => function ($query) {
                            $query->select('id', 'idcalendario', 'idempleado');
                            $query->with(['egresoEmpleado' => function ($query) {
                                $query->select('id', 'nombres');
                            }]);
                        }]);
                    }])
                    ->with(['credito_transfer' => function ($query) {
                        $query->with(['materialdetalle' => function ($query) {
                            $query->select('id', 'descripcion');
                        }]);
                        $query->select('id', 'idegreso', 'idmaterial', 'movimiento', 'fecha_salida', 'cantidad', 'id_origen', 'debito');
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

    public function deleteCreditSaldo(Request $request)
    {
        try {
            $id = $request->get('id');
            $credito = EgresoBodegaDetalle::where('id', $id)->first();
            DB::beginTransaction();
            if (is_object($credito)) {
                $total_credito = $credito->cantidad;
                //Sacar el id del empleado, el calendario del movimiento, material.
                $cabecera_credito = EgresoBodega::where(['id' => $credito->idegreso])->first();

                if (is_object($cabecera_credito)) {
                    $empleado_credito = $cabecera_credito->idempleado;
                    $calendario_credito = $cabecera_credito->idcalendario;
                    $material_credito = $credito->idmaterial;

                    //Buscamos el inventario donde vamos a restar el saldo que se acredito.
                    $inventario = InventarioEmpleado::where([
                        'idempleado' => $empleado_credito,
                        'idcalendar' => $calendario_credito,
                        'idmaterial' => $material_credito
                    ])->first();

                    if (is_object($inventario)) {
                        //Debitamos el saldo
                        $inventario->tot_egreso -= $total_credito;
                        if ($inventario->tot_egreso >= 0) {
                            $procesado = false;
                            $inventario->sld_final = ($inventario->sld_inicial + $inventario->tot_egreso) - $inventario->tot_devolucion;
                            if ($inventario->sld_final > 0) {
                                $inventario->updated_at = Carbon::now()->format(config('constants.format_date'));
                                $inventario->save();
                                $procesado = true;
                            } else if ($inventario->sld_final == 0) {
                                $inventario->delete();
                                $procesado = true;
                            }

                            if ($procesado) {
                                //Una vez procesado eliminamos el credito
                                $credito->delete();

                                //Consultar si fue mi ultimo registro
                                $ultimo_registro = EgresoBodegaDetalle::where(['idegreso' => $cabecera_credito->id])->get();
                                if (count($ultimo_registro) == 0) {
                                    $cabecera_credito->delete();
                                }

                                //Acreditamos el saldo debitado
                                $debito = EgresoBodegaDetalle::where(['id' => $credito->id_origen])->first();
                                if (is_object($debito)) {
                                    $total_debito = $debito->cantidad;
                                    if ($total_debito >= $total_credito) {
                                        $debito->cantidad -= $total_credito;

                                        if ($debito->cantidad == 0) {
                                            $debito->delete();
                                        } else {
                                            $debito->updated_at = Carbon::now()->format(config('constants.format_date'));
                                            $debito->save();
                                        }

                                        $cabecera_debito = EgresoBodega::where(['id' => $debito->idegreso])->first();
                                        if (is_object($cabecera_debito)) {
                                            $empleado_debito = $cabecera_debito->idempleado;
                                            $calendario_debito = $cabecera_debito->idcalendario;
                                            $material_debito = $debito->idmaterial;
                                            //Buscamos el inventario donde vamos a restar el saldo que se acredito.
                                            $inventario_debito = InventarioEmpleado::where([
                                                'idempleado' => $empleado_debito,
                                                'idcalendar' => $calendario_debito,
                                                'idmaterial' => $material_debito
                                            ])->first();

                                            if (is_object($inventario_debito)) {
                                                //Subimos inventario
                                                $inventario_debito->tot_egreso += $total_credito;
                                            } else {
                                                //Generamos un inventario
                                                $emplado = Empleado::where(['id' => $empleado_debito])->first();

                                                $inventario_debito = new InventarioEmpleado();
                                                $inventario_debito->codigo = $this->codigoTransaccionInventario($emplado->idhacienda);
                                                $inventario_debito->idcalendar = $calendario_debito;
                                                $inventario_debito->idempleado = $empleado_debito;
                                                $inventario_debito->idmaterial = $material_debito;
                                                $inventario_debito->sld_inicial = 0;
                                                $inventario_debito->tot_egreso = $total_credito;
                                                $inventario_debito->tot_devolucion = 0;
                                                $inventario_debito->created_at = Carbon::now()->format(config('constants.format_date'));
                                            }

                                            $inventario_debito->sld_final = ($inventario_debito->sld_inicial + $inventario_debito->tot_egreso) - $inventario_debito->tot_devolucion;
                                            $inventario_debito->updated_at = Carbon::now()->format(config('constants.format_date'));
                                            $inventario_debito->save();

                                            //Consultar si fue mi ultimo registro
                                            $ultimo_registro = EgresoBodegaDetalle::where(['idegreso' => $cabecera_debito->id])->get();
                                            if (count($ultimo_registro) == 0) {
                                                $cabecera_debito->delete();
                                            }

                                            $this->out = $this->respuesta_json('success', 200, 'Movimiento eliminado con exito!');
                                            DB::commit();
                                            return response()->json($this->out, 200);

                                        } else {
                                            throw new \Exception('Error, no se encontro la transaccion principal del debitante.');
                                        }
                                    } else {
                                        throw new \Exception('Error, el debito no puede quedar negativo.');
                                    }
                                } else {
                                    throw new \Exception('Error, no se ha encontrado el origen de este credito, vuelva a intentarlo mas tarde.');
                                }
                            } else {
                                throw new \Exception('Error, el saldo no puede ser negativo.');
                            }

                        } else {
                            throw new \Exception('Error, el egreso total no puede ser menor despues de eliminar el saldo acreditado.');
                        }
                    } else {
                        throw new \Exception('Error, no se ha encontrado el inventario donde se acredito el saldo.');
                    }
                } else {
                    throw new \Exception('No se encontro la transaccion principal del acreditado.');
                }
            } else {
                throw new \Exception('No existe este movimiento.');
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }

    }

    public function codigoTransaccion($hacienda = 1)
    {
        $transacciones = EgresoBodega::select('codigo')->get();
        $path = $hacienda == 1 ? 'PRI' : 'SFC';
        $codigo = $path . '-' . str_pad(count($transacciones) + 1, 10, "0", STR_PAD_LEFT);;
        return $codigo;
    }

    public function saldoAcreditadoDebitado($dataTransfer)
    {
        if (is_object($dataTransfer) && !empty($dataTransfer)) {
            $params_array = [
                'emp_solicitado' => $dataTransfer->emp_solicitado,
                'emp_recibe' => $dataTransfer->emp_recibe,
                'hacienda' => $dataTransfer->hacienda,
                'id_inventario_tomado' => $dataTransfer->id_inventario_tomado,
                'cantidad' => $dataTransfer->cantidad,
                'sld_final' => $dataTransfer->sld_final,
                'time' => $dataTransfer->time
            ];

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
                $timestamp = strtotime(str_replace('/', '-', $params_array['time']));
                $params_array['time'] = date(config('constants.date'), $timestamp);

                //Transferencia
                //Buscamos el dato de donde se va a transferir
                $inventario = InventarioEmpleado::where('id', $params_array['id_inventario_tomado'])->first();
                if (is_object($inventario)) {
                    //Se le resta del inventario
                    $inventario->tot_egreso -= $params_array['cantidad'];
                    $inventario->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);
                    $inventario->updated_at = Carbon::now()->format(config('constants.format_date'));
                    $inventario->save();
                }

                $calendario = Calendario::where('fecha', $params_array['time'])->first();

                $egreso = null;
                $movimiento = null;

                //Generamos el movimiento de transferencia (-)
                $egreso = EgresoBodega::where([
                    'idempleado' => $params_array['emp_solicitado']['id'],
                    'idcalendario' => $calendario->codigo
                ])->first();

                if (!is_object($egreso)) {
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

                $movimiento = EgresoBodegaDetalle::where([
                    'idmaterial' => $inventario->idmaterial,
                    'movimiento' => 'DEBIT-SAL',
                    'debito' => true
                ])->first();

                if (!is_object($movimiento)) {
                    $movimiento = new EgresoBodegaDetalle();
                    $movimiento->idegreso = $egreso->id;
                    $movimiento->idmaterial = $inventario->idmaterial;
                    $movimiento->movimiento = 'DEBIT-SAL';
                    $movimiento->debito = true;
                    $movimiento->fecha_salida = $params_array['time'];
                    $movimiento->cantidad = $params_array['cantidad'];
                    $movimiento->created_at = Carbon::now()->format(config('constants.format_date'));
                } else {
                    $movimiento->cantidad += $params_array['cantidad'];
                }

                $movimiento->updated_at = Carbon::now()->format(config('constants.format_date'));
                $movimiento->save();


                /*if ($inventario->sld_final == 0) {
                    $inventario->delete();
                }*/

                //Pasamos el saldo al inventario correspondiente como nuevo item,
                $inventario_traspaso = InventarioEmpleado::where([
                    'idcalendar' => $calendario->codigo,
                    'idempleado' => $params_array['emp_recibe']['id'],
                    'idmaterial' => $inventario->idmaterial
                ])->first();

                if (!is_object($inventario_traspaso)) {
                    $inventario_traspaso = new InventarioEmpleado();
                    $inventario_traspaso->codigo = $this->codigoTransaccionInventario($params_array['hacienda']);
                    $inventario_traspaso->idcalendar = $calendario->codigo;
                    $inventario_traspaso->idempleado = $params_array['emp_recibe']['id'];
                    $inventario_traspaso->idmaterial = $inventario->idmaterial;
                    $inventario_traspaso->sld_inicial = 0;
                    $inventario_traspaso->tot_egreso = $params_array['cantidad'];
                    $inventario_traspaso->tot_devolucion = 0;
                    $inventario_traspaso->created_at = Carbon::now()->format(config('constants.format_date'));
                } else {
                    $inventario_traspaso->tot_egreso += $params_array['cantidad'];
                }

                $inventario_traspaso->sld_final = (intval($inventario_traspaso->sld_inicial) + intval($inventario_traspaso->tot_egreso)) - intval($inventario_traspaso->tot_devolucion);
                $inventario_traspaso->updated_at = Carbon::now()->format(config('constants.format_date'));
                $inventario_traspaso->save();

                //si la fecha ya esta registrada con ese detalle se lo edita, caso contrario va como nuevo
                //Generamos el movimiento de transferencia (+)
                $egreso_recibido = EgresoBodega::where([
                    'idempleado' => $params_array['emp_recibe']['id'],
                    'idcalendario' => $calendario->codigo
                ])->first();

                if (!is_object($egreso_recibido)) {
                    //crear el movimiento con cabecera y detalle completo
                    $egreso_recibido = new EgresoBodega();
                    $egreso_recibido->codigo = $this->codigoTransaccion($params_array['hacienda']);
                    $egreso_recibido->idcalendario = $calendario->codigo;
                    $egreso_recibido->periodo = $calendario->periodo;
                    $egreso_recibido->semana = $calendario->semana;
                    $egreso_recibido->fecha = $params_array['time'];
                    $egreso_recibido->idempleado = $params_array['emp_recibe']['id'];
                    $egreso_recibido->created_at = Carbon::now()->format(config('constants.format_date'));
                    $egreso_recibido->updated_at = Carbon::now()->format(config('constants.format_date'));
                    $egreso_recibido->save();
                }

                //Cada que se hace un credito se lo registra como un nuevo egreso
                /*$detalle = EgresoBodegaDetalle::where([
                    'movimiento' => 'CREDIT-SAL',
                    'fecha_salida' => $params_array['time'],
                    'idegreso' => $egreso_recibido->id,
                    'id_origen' => $movimiento->id,
                    'debito' => false
                ])->first();

                if (!is_object($detalle)) {
                    $detalle = new EgresoBodegaDetalle();
                    $detalle->idegreso = $egreso_recibido->id;
                    $detalle->idmaterial = $inventario->idmaterial;
                    $detalle->movimiento = 'CREDIT-SAL';
                    $detalle->debito = false;
                    $detalle->fecha_salida = $params_array['time'];
                    $detalle->cantidad = $params_array['cantidad'];
                    $detalle->id_origen = $movimiento->id;
                    $detalle->created_at = Carbon::now()->format(config('constants.format_date'));
                } else {
                    $detalle->cantidad += $params_array['cantidad'];
                }*/

                $detalle = new EgresoBodegaDetalle();
                $detalle->idegreso = $egreso_recibido->id;
                $detalle->idmaterial = $inventario->idmaterial;
                $detalle->movimiento = 'CREDIT-SAL';
                $detalle->debito = false;
                $detalle->fecha_salida = $params_array['time'];
                $detalle->cantidad = $params_array['cantidad'];
                $detalle->id_origen = $movimiento->id;
                $detalle->created_at = Carbon::now()->format(config('constants.format_date'));
                $detalle->updated_at = Carbon::now()->format(config('constants.format_date'));
                $detalle->save();

                return true;

            } else {
                return false;
            }
        }
        return false;
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
            $status = false;
            $eliminar_cabecera = false;
            $detalle_egreso = EgresoBodegaDetalle::where(['id' => $id, 'movimiento' => 'EGRE-ART'])->first();
            if (is_object($detalle_egreso)) {
                DB::beginTransaction();
                $detalle_egreso->delete();

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

                if (is_object($inventario)) {
                    $inventario->tot_egreso = intval($inventario->tot_egreso) - intval($detalle_egreso->cantidad);
                    $inventario->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);

                    if ($inventario->sld_final < 0) {
                        throw new \Exception('No se puede eliminar este movimiento, su saldo es menor al que se esta eliminando.');
                    }

                    $status = $inventario->save();

                    if (intval($inventario->sld_final) === 0)
                        $status = $inventario->delete();
                }

                if (!$status) {
                    throw new \Exception('No se puede eliminar el registro, su saldo se encuentra distribuido.');
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
        //Para saber si cuantas veces ha sido transferido este egreso, para no editar este valor.
        $egresos = EgresoBodegaDetalle::where('id_origen', $detalle_egreso->id)->get();
        $cantidad_transferidas = count($egresos);

        if ($cantidad_transferidas > 0) {
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
