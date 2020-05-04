<?php

namespace App\Http\Controllers\Bodega;

use App\Http\Controllers\Controller;
use App\Models\Bodega\EgresoBodega;
use App\Models\Bodega\EgresoBodegaDetalle;
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
                    if (is_object($calendario)) {
                        //Se registra la cabecera
                        $existe_egreso = EgresoBodega::where([
                            'periodo' => $calendario->periodo,
                            'semana' => $calendario->semana,
                            'idempleado' => $cabecera['empleado']['id']
                        ])->first();
                        if (empty($existe_egreso) || is_null($existe_egreso) || !is_object($existe_egreso)) {
                            $egreso = new EgresoBodega();
                            $egreso->codigo = $this->codigoTransaccion(intval($cabecera['hacienda']));
                            $egreso->idempleado = $cabecera['empleado']['id'];
                            $egreso->periodo = $calendario->periodo;
                            $egreso->semana = $calendario->semana;
                            $egreso->created_at = Carbon::now()->format("d-m-Y H:i:s");
                            $egreso->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                            $egreso->save();

                            foreach ($detalle as $item) {
                                $this->storeDetalleTransaccion($item, $egreso->id);
                            }

                            $this->out = $this->respuesta_json('success', 200, 'Transaccion ' . $egreso->codigo . ' registrada correctamente');
                            $this->out['codigo_transaccion'] = $egreso->codigo;
                        } else {
                            foreach ($detalle as $item) {
                                $this->storeDetalleTransaccion($item, $existe_egreso->id);
                            }
                            $this->out = $this->respuesta_json('success', 200, 'Transaccion ' . $existe_egreso->codigo . ' actualizada correctamente');
                        }

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
                        ->with(['egresoDetalle' => function($query){
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
