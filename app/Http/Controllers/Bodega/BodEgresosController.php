<?php

namespace App\Http\Controllers\Bodega;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hacienda\InventarioEmpleadoController;
use App\Models\Bodega\Bodega;
use App\Models\Bodega\EgresoBodega;
use App\Models\Bodega\EgresoBodegaDetalle;
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
                $validacion = $this->validationModel($params_array);

                if (!$validacion->fails()) {
                    //Procesar la cabecera
                    $cabecera = $params_array['cabecera'];
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
                        $despachos = $params_array['detalle'];

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

                        DB::commit();
                        $this->out = $this->respuesta_json('success', 200, 'Despacho registrado con éxito!!!');
                    } else {
                        throw new \Exception('Ya se ha registrado un egreso para este empleado.');
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
            if (!empty($fecha) && !is_null($fecha)) {
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
                            $query->select('id', 'idegreso', 'fecha_salida as fecha', 'idmaterial', 'cantidad');
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

            if (!empty($id) && !is_null($id)) {
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
