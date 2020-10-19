<?php

namespace App\Http\Controllers\Bodega;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hacienda\InventarioEmpleadoController;
use App\Models\Bodega\Bodega;
use App\Models\Bodega\EgresoBodega;
use App\Models\Bodega\EgresoBodegaDetalle;
use App\Models\Bodega\EgresoBodegaDetalleTransfer;
use App\Models\Hacienda\InventarioEmpleado;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BodEgresosController extends Controller
{
    protected $out;

    public function __construct()
    {
        //$this->middleware('api.auth', ['except' => ['index', 'show', 'customSelect', 'getOptions']]);
        $this->out = $this->respuesta_json('error', 500, 'Error en respuesta desde el servidor.');
    }

    public function index()
    {

    }

    public function store(Request $request)
    {
        try {
            $json = $request->input('json', null);
            $params_array = json_decode($json, true);
            if (!empty($params_array) && count($params_array) > 0) {
                DB::beginTransaction();
                $validacion = Validator::make($params_array, [
                    'cabecera.idempleado' => 'required',
                    'cabecera.hacienda' => 'required',
                ], [
                    'cabecera.empleado.id.required' => "El empleado es necesario",
                    'cabecera.hacienda.required' => "La hacienda es necesaria",
                ]);;

                if (!$validacion->fails()) {
                    //Procesar la cabecera
                    $cabecera = $params_array['cabecera'];
                    $despachos = $params_array['detalle'];
                    $transferencias = $params_array['transferencias'];

                    if (count($despachos) > 0 || count($transferencias) > 0) {
                        //Convertir fecha
                        $timestamp = strtotime(str_replace('/', '-', $cabecera['fecha']));
                        $cabecera['fecha'] = date(config('constants.date'), $timestamp);

                        $egreso = new EgresoBodega();
                        $egreso->fecha_apertura = $cabecera['fecha'];
                        $egreso->idempleado = $cabecera['idempleado'];
                        $egreso->parcial = false;
                        $egreso->final = false;
                        $egreso->created_at = Carbon::now()->format(config('constants.format_date'));
                        $egreso->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $egreso->estado = true;

                        if (!EgresoBodega::existe($egreso)) {
                            $egreso->save();
                            //Procesar los materiales despachados
                            foreach ($despachos as $despacho) {
                                $timestamp = strtotime(str_replace('/', '-', $despacho['time']));
                                $despacho['time'] = date(config('constants.date'), $timestamp);

                                $egreso_detalle = new EgresoBodegaDetalle();
                                $egreso_detalle->idegreso = $egreso->id;
                                $egreso_detalle->idmaterial = $despacho['idmaterial'];
                                $egreso_detalle->fecha_salida = $despacho['time'];
                                $egreso_detalle->cantidad = $despacho['cantidad'];
                                $egreso_detalle->created_at = Carbon::now()->format(config('constants.format_date'));
                                $egreso_detalle->updated_at = Carbon::now()->format(config('constants.format_date'));
                                $egreso_detalle->estado = true;
                                $egreso_detalle->save();

                                //Procesar Inventario de empleado
                                InventarioEmpleadoController::storeInventario($egreso->idempleado, $egreso_detalle);
                                //return response()->json($inventario);
                            }

                            $this->out = $this->respuesta_json('success', 200, 'Despacho registrado con éxito!!!');

                            //Procesar transferencias de saldo en caso de existir
                            if (!empty($transferencias) && count($transferencias) > 0) {
                                //Proceso de transferencias
                                $resultado_transferencias = $this->SaldoTransfer($transferencias, $egreso);
                                $this->out['transferencias'] = $resultado_transferencias;
                            }

                            DB::commit();
                        } else {
                            throw new \Exception('Ya se ha registrado un egreso para este empleado.');
                        }
                    } else {
                        throw new \Exception('No se puede procesar la transacción.');
                    }
                } else {
                    $this->out['validacion'] = $validacion->errors()->all();
                    throw new \Exception('Error en la validacion de datos.');
                }
            } else {
                throw new \Exception('No se han enviado todos los parametros');
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['error'] = $ex->getMessage();
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function show($id)
    {
        //
    }

    public function showByEmpleado(Request $request, $idempleado)
    {
        try {
            $fecha = $request->get('fecha', null);
            if (!empty($fecha)) {
                $existe = EgresoBodega::existeByEmpleado($idempleado, $fecha);
                if (is_object($existe)) {
                    $egreso = EgresoBodega::from("BOD_EGRESOS as egreso")
                        ->select('egreso.id', 'egreso.fecha_apertura as fecha', 'idempleado', 'parcial', 'final')
                        ->where('egreso.id', $existe->id)
                        ->with(['egresoEmpleado' => function ($query) {
                            $query->select('id', 'nombres as descripcion', 'idhacienda', 'idlabor', 'estado');
                            $query->with(['hacienda' => function ($query) {
                                $query->select('id', 'detalle as descripcion', 'ruc');
                            }]);
                        }])
                        ->with(['egresoDetalle' => function ($query) {
                            $query->select('id', 'idegreso', 'fecha_salida as fecha', 'idmaterial', 'cantidad', 'movimiento');
                            $query->with(['materialdetalle' => function ($query) {
                                $query->select('id', 'codigo', 'descripcion', 'stock');
                            }]);
                        }])
                        ->first();

                    if (is_object($egreso)) {
                        $this->out = $this->respuesta_json('success', 200, 'Ya tiene despacho para esta semana');
                        $this->out['transaccion'] = $egreso;
                    }
                } else {
                    throw new \Exception('No se encontraron despachos para esta semana.');
                }
            } else {
                throw new \Exception('No se ha recibido la fecha.');
            }

        } catch (\Exception $ex) {
            $this->out['error'] = $ex->getMessage();
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function update(Request $request, $id)
    {
        try {
            $json = $request->input('json', null);
            $params_array = json_decode($json, true);
            $egreso = EgresoBodega::existeById($id);

            if (!empty($id)) {
                if (!empty($params_array) && count($params_array) > 0) {
                    if (is_object($egreso)) {
                        //Solo atualizamos el detalle
                        DB::beginTransaction();
                        $validacion = $this->validationModel($params_array);
                        if (!$validacion->fails()) {
                            //Actualizamos el momento de edicion
                            $egreso->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $egreso->save();

                            $despachos = $params_array['detalle'];
                            $romper_ciclo_detalle = '';
                            foreach ($despachos as $despacho) {
                                $timestamp = strtotime(str_replace('/', '-', $despacho['time']));
                                $despacho['time'] = date(config('constants.date'), $timestamp);

                                $egreso_detalle = new EgresoBodegaDetalle();
                                $egreso_detalle->idegreso = $egreso->id;
                                $egreso_detalle->idmaterial = $despacho['idmaterial'];
                                $egreso_detalle->movimiento = 'EGRE-ART';
                                $egreso_detalle->fecha_salida = $despacho['time'];
                                $egreso_detalle->cantidad = $despacho['cantidad'];
                                $egreso_detalle->estado = true;
                                $egreso_detalle->created_at = Carbon::now()->format(config('constants.format_date'));

                                $save = true;
                                $edit = false;

                                //En caso de que el item exista. ejemplo:
                                /*
                                 * Se tiene un despacho para el mismo item:
                                 * 12/10/2020 -> 10
                                 * 13/10/2020 -> 30
                                 * 14/10/2020 -> 40
                                 *
                                 * el 13/10/2020 se realiza una edicion, se debe cambiar los 30 por 20
                                 *
                                 * para actualizar el inventario, al total se le resta la cantidad anterior y se le añade la
                                 * nueva cantidad
                                 * $inventario->tot_egreso = ($inventario->tot_egreso - $cantidad_a_saldar) + $detalle['cantidad'];
                                 * */

                                $existe = EgresoBodegaDetalle::existe($egreso_detalle);
                                if (is_object($existe)) {
                                    //Verificar si no es un detalle por transferencia
                                    $is_Transferencia = EgresoBodegaDetalleTransfer::existTransferbyDetalleEgreso($existe->id);
                                    if (!is_object($is_Transferencia)) {
                                        $egreso_detalle = $existe;

                                        //Consultar si se ha hecho una transferencia de este egreso.
                                        if (isset($despacho['delete']) && filter_var($despacho['delete'], FILTER_VALIDATE_BOOL)) {
                                            //Eliminar item.

                                            $respuesta = InventarioEmpleadoController::reduceInventario($egreso->idempleado, $egreso_detalle);

                                            if ($respuesta['negativo']) {
                                                InventarioEmpleadoController::storeInventario($egreso->idempleado, $egreso_detalle, true);
                                                $romper_ciclo_detalle = "Un registro no se pudo eliminar, el consumo no puede ser mayor a lo que se despacho.";
                                                break;
                                            }

                                            $egreso_detalle->delete();

                                        } else {
                                            //Editar item
                                            $cantidad_a_saldar = $egreso_detalle->cantidad;
                                            $egreso_detalle->cantidad = $despacho['cantidad'];

                                            //Actualizacion de los items de despacho
                                            //Procesar Inventario de empleado
                                            $respuesta = InventarioEmpleadoController::storeInventario($egreso->idempleado,
                                                $egreso_detalle, false, $cantidad_a_saldar);

                                            if ($respuesta['negativo']) {
                                                $romper_ciclo_detalle = "Un registro no se pudo editar, el consumo no puede ser mayor a lo que se despacho.";
                                                break;
                                            }

                                            $egreso_detalle->updated_at = Carbon::now()->format(config('constants.format_date'));
                                            $egreso_detalle->save();
                                        }
                                    }
                                } else {
                                    //Edicion de transaccion, pero se añadio un nuevo item de despacho
                                    $egreso_detalle->updated_at = Carbon::now()->format(config('constants.format_date'));
                                    $egreso_detalle->save();
                                    //Procesar Inventario de empleado por incremento
                                    InventarioEmpleadoController::storeInventario($egreso->idempleado, $egreso_detalle, true);
                                }
                            }

                            if (EgresoBodegaDetalle::rowsItems($egreso->id) == 0) {
                                $egreso->delete();
                            }

                            $this->out = $this->respuesta_json('success', 200, 'Despacho actualizado con éxito!!!');
                            $this->out['error_ciclo'] = $romper_ciclo_detalle;

                            //Procesar transferencias de saldo en caso de existir
                            $transferencias = $params_array['transferencias'];
                            if (!empty($transferencias) && count($transferencias) > 0) {
                                //Proceso de transferencias
                                $resultado_transferencias = $this->SaldoTransfer($transferencias, $egreso);
                                $this->out['transferencias'] = $resultado_transferencias;
                            }

                            DB::commit();
                        } else {
                            $this->out['validacion'] = $validacion->errors()->all();
                            throw new \Exception('Error en la validacion de datos.');
                        }
                    } else {
                        throw new \Exception('Transacción no existe.');
                    }
                } else {
                    throw new \Exception('No se han enviado todos los parametros');
                }
            } else {
                throw new \Exception('El id de la transacción es invalida.');
            }

        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['error'] = $ex->getMessage();
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function destroy($id)
    {
        try {
            if (is_object($existe = EgresoBodega::existeById($id))) {
                DB::beginTransaction();
                $eliminar = false;

                //Eliminar detalles
                $detalles = EgresoBodegaDetalle::where(['idegreso' => $id])->get();

                foreach ($detalles as $detalle) {
                    $no_hay_transferencia = true;
                    //Consultar si detalles no han sido transferidos

                    if ($no_hay_transferencia) {
                        //Actualizar inventario
                        $respuesta = InventarioEmpleadoController::reduceInventario($existe->idempleado, $detalle);

                        if ($respuesta['negativo']) {
                            $eliminar = false;
                            break;
                        }

                        //Eliminar registro
                        $eliminar = $detalle->delete();
                    }
                }

                //Eliminar cabecera
                if ($eliminar) {
                    $existe->delete();
                    DB::commit();
                    $this->out = $this->respuesta_json('success', 200, 'Egreso eliminado con éxito!!!');
                } else {
                    throw new \Exception('No se puedo eliminar esta transacción.');
                }
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            $this->out['error'] = $ex->getMessage();
        }

        return response()->json($this->out, $this->out['code']);
    }

    //Transferencias
    public function saldosEmpleado(Request $request)
    {
        try {
            $grupo_materiales = $request->get('grupo', null);
            $idempleado = $request->get('empleado', null);

            if (!empty($grupo_materiales) && !empty($idempleado)) {
                $empleado = InventarioEmpleado::select('id', 'idcalendar', 'idempleado', 'idmaterial', 'sld_final')
                    ->where([
                        'idempleado' => $idempleado,
                        'estado' => true
                    ])->whereHas('material', function ($query) use ($grupo_materiales) {
                        $query->where('idgrupo', $grupo_materiales);
                    })->with(['material' => function ($query) {
                        $query->select('id', 'codigo', 'descripcion', 'stock');
                    }])
                    ->where('sld_final', '>', 0)
                    ->get();

                $this->out = $this->respuesta_json('success', 200, "Saldos encontrados.");
                $this->out['saldos'] = $empleado;
            } else {
                throw new \Exception("No se han enviado todos los parametros");
            }

        } catch (\Exception $ex) {
            $this->out['error'] = $ex->getMessage();
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function modelTransferenciatoEgreso($data, $id_cabecera_egreso, $movimiento = 'CREDIT-SLD') //ACREDITACION DE SALDO
    {
        $timestamp = strtotime(str_replace('/', '-', $data['time']));
        $data['time'] = date(config('constants.date'), $timestamp);

        $egreso_detalle = new EgresoBodegaDetalle();
        $egreso_detalle->idegreso = $id_cabecera_egreso;
        $egreso_detalle->idmaterial = $data['idmaterial'];
        $egreso_detalle->fecha_salida = $data['time'];
        $egreso_detalle->cantidad = $data['cantidad'];
        $egreso_detalle->movimiento = $movimiento;
        $egreso_detalle->created_at = Carbon::now()->format(config('constants.format_date'));
        $egreso_detalle->updated_at = Carbon::now()->format(config('constants.format_date'));
        $egreso_detalle->estado = true;

        return $egreso_detalle;
    }

    public function SaldoTransfer($transferencias, $egreso)
    {
        $save = false;
        $respuestas = [];

        foreach ($transferencias as $transferencia) {
            //Guardamos en la tabla de transferencias
            //Registramos el debito al empleado
            $transferencia_credito = $this->modelTransferenciatoEgreso($transferencia, $egreso->id); //CREDITO
            $existe = EgresoBodegaDetalle::existe($transferencia_credito);
            if (is_object($existe)) {
                $transferencia_credito = $existe;
                $cantidad_saldar = $transferencia_credito->cantidad;
                $transferencia_credito->cantidad += $transferencia['cantidad'];
                $save = InventarioEmpleadoController::updateInventarioByTransferSaldo($egreso->idempleado, $transferencia_credito, $cantidad_saldar)['status'];
            } else {
                $save = InventarioEmpleadoController::updateInventarioByTransferSaldo($egreso->idempleado, $transferencia_credito)['status'];
            }


            if ($save) {
                $save = $transferencia_credito->save();
                if ($save) {
                    //Guardamos la transferrencia
                    $saldo_inv_transfer = new EgresoBodegaDetalleTransfer();
                    $saldo_inv_transfer->idEgreso = $transferencia_credito->id;
                    $saldo_inv_transfer->idInvEmp = $transferencia['idInv'];
                    $saldo_inv_transfer->cantidad = $transferencia['cantidad'];
                    $saldo_inv_transfer->debito = true;
                    $saldo_inv_transfer->created_at = Carbon::now()->format(config('constants.format_date'));

                    //Procedemos al debito de los inventarios de donde se ha tomado el saldo
                    $inventario = InventarioEmpleado::where(['id' => $transferencia['idInv']])->first();
                    $existe_transfer = EgresoBodegaDetalleTransfer::existTransfer($saldo_inv_transfer);

                    if (is_object($existe_transfer)) {
                        $saldo_inv_transfer = $existe_transfer;
                        //Reduce el inventario
                        $inventario->tot_devolucion += $transferencia['cantidad'];
                        //Edita el registro
                        $saldo_inv_transfer->cantidad += $transferencia['cantidad'];
                    } else {
                        $inventario->tot_devolucion = $transferencia['cantidad'];
                    }

                    $inventario->sld_final = ($inventario->sld_inicial + $inventario->tot_egreso) - ($inventario->tot_consumo + $inventario->tot_devolucion);
                    $save = $inventario->save();

                    if ($save) {
                        $saldo_inv_transfer->updated_at = Carbon::now()->format(config('constants.format_date'));
                        $save = $saldo_inv_transfer->save();
                    }
                }

                array_push($respuestas, [
                    'save' => $save,
                    'transferencia' => $transferencia
                ]);
            }
        }

        return $respuestas;
    }

    public function validationModel($params_array)
    {
        return Validator::make($params_array, [
            'cabecera.idempleado' => 'required',
            'cabecera.hacienda' => 'required',
            'detalle' => 'required|array',
        ], [
            'cabecera.empleado.id.required' => "El empleado es necesario",
            'cabecera.hacienda.required' => "La hacienda es necesaria",
            'detalle.required' => "No se ha seleccionado ningun material"
        ]);
    }

    public function respuesta_json($status, $code, $message)
    {
        return array(
            'status' => $status,
            'code' => $code,
            'message' => $message
        );
    }

}
