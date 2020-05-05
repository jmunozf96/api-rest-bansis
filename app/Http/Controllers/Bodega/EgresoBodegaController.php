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

                            foreach ($detalle as $item) {
                                $this->storeDetalleTransaccion($item, $egreso->id);
                                $this->storeInventario($cabecera, $item);
                            }
                            $mensaje = 'Se registro correctamente la transaccion #' . $egreso->codigo;
                            $this->out['codigo_transaccion'] = $egreso->codigo;
                        } else {
                            foreach ($detalle as $item) {
                                $this->storeDetalleTransaccion($item, $existe_egreso->id);
                                $this->storeInventario($cabecera, $item);
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

    public function storeDetalleTransaccion($detalle, $idEgreso)
    {
        try {
            $existe_detalle = EgresoBodegaDetalle::where([
                'idegreso' => $idEgreso,
                'idmaterial' => $detalle['idmaterial'],
                'fecha_salida' => $detalle['time']
            ])->first();
            if (is_object($existe_detalle)) {
                if (intval($existe_detalle->cantidad) !== intval($detalle['cantidad'])) {
                    $existe_detalle->cantidad = $detalle['cantidad'];
                    $existe_detalle->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                    $existe_detalle->update();
                }
            } else {
                $egreso_detalle = new EgresoBodegaDetalle();
                $egreso_detalle->idegreso = $idEgreso;
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

    public function storeInventario($cabecera, $detalle)
    {
        try {
            //Guardar el inventario
            $egreso_inventario = new \stdClass();
            $egreso_inventario->idcalendario = $cabecera['idcalendario'];
            $egreso_inventario->idempleado = $cabecera['empleado']['id'];
            $egreso_inventario->idhacienda = $cabecera['hacienda'];
            $egreso_inventario->idmaterial = $detalle['idmaterial'];
            $egreso_inventario->cantidad = $detalle['cantidad'];
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
                    $material_stock->stock = intval($material_stock->stock) - intval($egreso->cantidad);
                } else {
                    $material_stock->stock = (intval($material_stock->stock) + intval($inventario->tot_egreso)) - intval($egreso->cantidad);
                }

                $inventario->tot_egreso = $egreso->cantidad;
                $inventario->tot_devolucion = 0;
                $inventario->sld_final = (intval($inventario->sld_inicial) + intval($inventario->tot_egreso)) - intval($inventario->tot_devolucion);
                $inventario->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                $inventario->save();
                $material_stock->update();

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
        $path = $hacienda === 1 ? 'PRI-INV-' : 'SFC-INV-';
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

    public function respuesta_json(...$datos)
    {
        return array(
            'status' => $datos[0],
            'code' => $datos[1],
            'message' => $datos[2]
        );
    }
}
