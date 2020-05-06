<?php

namespace App\Http\Controllers\Bodega;

use App\Http\Controllers\Controller;
use App\Models\Bodega\EgresoBodega;
use App\Models\Bodega\EgresoBodegaDetalle;
use App\Models\Bodega\Material;
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
        $this->middleware('api.auth', ['except' => ['index', 'show', 'getTransaccion']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
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

                    $calendario = Calendario::where('fecha', $params_array['time'])->first();

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
                            $egreso->idempleado = $cabecera['empleado']['id'];
                            $egreso->idcalendario = $calendario->codigo;
                            $egreso->periodo = $calendario->periodo;
                            $egreso->semana = $calendario->semana;
                            $egreso->created_at = Carbon::now()->format("d-m-Y H:i:s");
                            $egreso->updated_at = Carbon::now()->format("d-m-Y H:i:s");
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

            $existe_detalle = EgresoBodegaDetalle::where([
                'idegreso' => $cabecera['id'],
                'idmaterial' => $detalle['idmaterial'],
                'fecha_salida' => $detalle['time']
            ])->first();

            if (is_object($existe_detalle)) {
                $this->storeInventario($cabecera, $detalle, true, $existe_detalle->cantidad);

                if (intval($existe_detalle->cantidad) !== intval($detalle['cantidad'])) {
                    $existe_detalle->cantidad = $detalle['cantidad'];
                    $existe_detalle->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                    $existe_detalle->update();
                }
            } else {
                $this->storeInventario($cabecera, $detalle, false);

                $egreso_detalle = new EgresoBodegaDetalle();
                $egreso_detalle->idegreso = $cabecera['id'];
                $egreso_detalle->idmaterial = $detalle['idmaterial'];
                //Añadir el movimiento que se esta realizando y llamar a funcion que va a identificar el tipo
                //de movimiento y ejecutara una accion respectiva
                $egreso_detalle->fecha_salida = $detalle['time'];
                $egreso_detalle->cantidad = $detalle['cantidad'];
                $egreso_detalle->created_at = Carbon::now()->format("d-m-Y H:i:s");
                $egreso_detalle->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                $egreso_detalle->save();
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
            $this->saveInventario($egreso_inventario);
            return true;
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
                    $inventario->created_at = Carbon::now()->format("d-m-Y H:i:s");
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

                $inventario->tot_devolucion = 0;
                $inventario->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);
                $inventario->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                $inventario->save();
                $material_stock->save();
                return true;
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
                $calendario = Calendario::where('fecha', $fecha)->first();
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

                    return response()->json($egreso, 200);
                }
            }

            throw new \Exception('No se encontraron datos para esta fecha');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 200);
        }
    }

    public function codigoTransaccion($hacienda = 1)
    {
        $transacciones = EgresoBodega::select('codigo')->get();
        $path = $hacienda === 1 ? 'PRI' : 'SFC';
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
                    'cantidad' => 'required|min=1'
                ], [
                    'emp_solicitado.required' => 'El empleado al que se le solicita el traspaso es requerido',
                    'emp_recibe.required' => 'El empleado que recibe el traspaso es requerido',
                    'id_inventario_tomado.required' => 'No se ha tomado ningun inventario',
                    'cantidad.required' => 'No hay cantidad',
                    'cantidad.min' => 'Debe ingresar una cantidad valida'
                ]);

                if (!$validacion->fails()) {
                    //Transferencia
                    //Buscamos el dato de donde se va a transferir
                    $inventario = InventarioEmpleado::where('id', $params_array['id_inventario_tomado'])->first();
                    if (is_object($inventario)) {
                        $inventario->tot_egreso -= $params_array['cantidad'];
                        $inventario->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);
                        $inventario->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                        $inventario->save();
                    }
                    //Generamos el movimiento de transferencia (-)
                    $egreso = EgresoBodega::where([
                        'idempleado' => $params_array['emp_solicitado']['id'],
                        'idcalendario' => $inventario->idcalendar
                    ])->first();
                    if (is_object($egreso)) {
                        $movimiento = new \stdClass();
                        $movimiento->idegreso = $egreso->id;
                        $movimiento->idmaterial = $inventario->idmaterial;
                        $movimiento->movimiento = 'TRASP-SAL';
                        $movimiento->fecha_salida = $params_array['time'];
                        $movimiento->cantidad = -$params_array['cantidad'];
                        $movimiento->created_at = Carbon::now()->format("d-m-Y H:i:s");
                        $movimiento->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                        $movimiento->save();
                    }
                    //Pasamos el saldo al inventario correspondiente como nuevo item,
                    $existe_inventario_transferir = InventarioEmpleado::where([
                        'idcalendar' => $inventario->idcalendar,
                        'idempleado' => $params_array['emp_recibe']['id'],
                        'idmaterial' => $inventario->idmaterial
                    ])->first();
                    if (!is_object($existe_inventario_transferir) && empty($existe_inventario_transferir)) {
                        $inventario_traspaso = new \stdClass();
                        $inventario_traspaso->codigo = $this->codigoTransaccionInventario($params_array['hacienda']);
                        $inventario_traspaso->idcalendar = $inventario->idcalendar;
                        $inventario_traspaso->idempleado = $params_array['emp_recibe']['id'];
                        $inventario_traspaso->idmaterial = $inventario->idmaterial;
                        $inventario_traspaso->sld_inicial = 0;
                        $inventario_traspaso->tot_egreso = $params_array['cantidad'];
                        $inventario_traspaso->tot_devolucion = 0;
                        $inventario_traspaso->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);
                        $inventario_traspaso->created_at = Carbon::now()->format("d-m-Y H:i:s");
                        $inventario_traspaso->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                        $inventario_traspaso->save();
                    } else {
                        $existe_inventario_transferir->tot_egreso += $params_array['cantidad'];
                        $existe_inventario_transferir->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);
                        $existe_inventario_transferir->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                        $existe_inventario_transferir->save();
                    }
                    //si la fecha ya esta registrada con ese detalle se lo edita, caso contrario va como nuevo
                    //Generamos el movimiento de transferencia (+)
                    $egreso_recibido = EgresoBodega::where([
                        'idempleado' => $params_array['emp_recibe']['id'],
                        'idcalendario' => $inventario->idcalendar
                    ])->first();
                    if (is_object($egreso_recibido)) {
                        $existe_detalle_transferir = EgresoBodegaDetalle::where([
                            'idegreso' => $egreso_recibido->id,
                            'idmaterial' => $inventario->idmaterial
                        ])->first();
                        if (is_object($existe_detalle_transferir)) {
                            $existe_detalle_transferir->cantidad += $params_array['cantidad'];
                            $existe_detalle_transferir->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                            $existe_detalle_transferir->save();
                        } else {
                            $detalle_transferir = new \stdClass();
                            $detalle_transferir->idegreso = $egreso_recibido->id;
                            $detalle_transferir->idmaterial = $inventario->idmaterial;
                            $detalle_transferir->movimiento = 'TRASP-SAL';
                            $detalle_transferir->fecha_salida = $params_array['time'];
                            $detalle_transferir->cantidad = -$params_array['cantidad'];
                            $detalle_transferir->created_at = Carbon::now()->format("d-m-Y H:i:s");
                            $detalle_transferir->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                            $detalle_transferir->save();
                        }
                    } else {
                        //crear el movimiento con cabecera y detalle completo
                    }

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

    public function destroy($id)
    {
        try {
            $detalle_egreso = EgresoBodegaDetalle::where(['id' => $id])->first();
            if (is_object($detalle_egreso)) {
                DB::beginTransaction();
                $detalle_egreso->delete();

                //Inventario
                $egreso = EgresoBodega::where(['id' => $detalle_egreso->idegreso])->first();
                $inventario = InventarioEmpleado::where([
                    'idcalendar' => $egreso->idcalendario,
                    'idempleado' => $egreso->idempleado,
                    'idmaterial' => $detalle_egreso->idmaterial
                ])->first();
                $inventario->tot_egreso = intval($inventario->tot_egreso) - intval($detalle_egreso->cantidad);
                $inventario->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);
                $inventario->save();

                if (intval($inventario->sld_final) === 0)
                    $inventario->delete();


                $material_stock = Material::where(['id' => $detalle_egreso->idmaterial])->first();
                $material_stock->stock += floatval($detalle_egreso->cantidad);
                $material_stock->save();
                DB::commit();

                $this->out = $this->respuesta_json('success', 200, 'Movimiento se ha eliminado correctamente, inventario actualizado.');
                return response()->json($this->out, $this->out['code']);
            }
            throw new \Exception('No se encontro el registro con este Id');
        } catch (\Exception $ex) {
            $this->out['code'] = 500;
            $this->out['message'] = 'No se puede eliminar el registro.';
            $this->out['error_message'] = $ex->getMessage();
            DB::rollBack();
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
