<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Bodega\EgresoBodegaDetalle;
use App\Models\Bodega\Material;
use App\Models\Hacienda\InventarioEmpleado;
use App\Models\Sistema\Calendario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use mysql_xdevapi\Exception;

class InventarioEmpleadoController
{
    protected static $out;

    public function __construct()
    {
        self::$out = $this->respuesta_json('error', 500, 'Error en respuesta desde el servidor.');
    }

    /**
     * @return array
     */
    public static function getOut(): array
    {
        return self::$out;
    }

    public static function setOut(array $out): void
    {
        self::$out = $out;
    }

    public static function storeInventario($idempleado, EgresoBodegaDetalle $detalle, $incrementa = false, $cantidad_a_saldar = 0)
    {
        try {
            $saldo_negativo = false;
            //Traemos los datos del calendario
            $calendario = self::calendario($detalle['fecha_salida']);

            $inventario = new InventarioEmpleado();
            $inventario->idcalendar = $calendario->codigo;
            $inventario->idempleado = $idempleado;
            $inventario->idmaterial = $detalle['idmaterial'];
            $inventario->sld_inicial = 0;
            $inventario->tot_egreso = $detalle['cantidad'];
            $inventario->tot_consumo = 0;
            $inventario->tot_devolucion = 0;
            $inventario->saldoFinal();
            $inventario->updated_at = Carbon::now()->format(config('constants.format_date'));

            $save = true;
            $existe = InventarioEmpleado::existeInventario($inventario);
            if (!is_object($existe)) {
                //Actualizar stock del material----------------------------------------------------
                $material_stock = Material::getMaterialById($inventario->idmaterial);
                $material_stock->stock -= $detalle['cantidad'];
                $material_stock->save();
                //---------------------------------------------------------------------------------

                $inventario->created_at = Carbon::now()->format(config('constants.format_date'));
                $inventario->save();
            } else {
                $save = false;

                $inventario = $existe;

                //Actualizar stock del material----------------------------------------------------
                $material_stock = Material::getMaterialById($inventario->idmaterial);
                $material_stock->stock += $inventario->tot_egreso;
                //---------------------------------------------------------------------------------

                if ($incrementa) {
                    $inventario->tot_egreso += $detalle['cantidad'];
                } else {
                    $inventario->tot_egreso = ($inventario->tot_egreso - $cantidad_a_saldar) + $detalle['cantidad'];
                }

                $material_stock->stock -= $inventario->tot_egreso;
                $material_stock->save();

                $inventario->saldoFinal();

                if ($inventario->sld_final < 0) {
                    $saldo_negativo = true;
                } else {
                    $inventario->save();
                }
                //edicion del inventario
            }

            self::setOut([
                'status' => $save,
                'negativo' => $saldo_negativo,
                'data' => $inventario
            ]);

        } catch (\Exception $ex) {
            self::setOut([
                'status' => 'error',
                'message' => $ex->getMessage()
            ]);
        }

        return self::getOut();
    }

    public static function reduceInventario($idempleado, EgresoBodegaDetalle $detalle)
    {
        try {
            //Traemos los datos del calendario
            $calendario = self::calendario($detalle['fecha_salida']);

            $inventario = new InventarioEmpleado();
            $inventario->idcalendar = $calendario->codigo;
            $inventario->idempleado = $idempleado;
            $inventario->idmaterial = $detalle['idmaterial'];

            $delete = false;
            $saldo_negativo = false;

            if (is_object(InventarioEmpleado::existeInventario($inventario))) {
                //Actualizar stock del material----------------------------------------------------
                $material_stock = Material::getMaterialById($inventario->idmaterial);
                $material_stock->stock += $detalle['cantidad'];
                $material_stock->save();
                //---------------------------------------------------------------------------------

                $inventario = InventarioEmpleado::existeInventario($inventario);
                $inventario->tot_egreso -= $detalle['cantidad'];
                $inventario->saldoFinal();
                $inventario->updated_at = Carbon::now()->format(config('constants.format_date'));
                $inventario->save();

                if ($inventario->sld_final == 0) {
                    $delete = $inventario->delete();
                } else if ($inventario->sld_final < 0) {
                    $saldo_negativo = true;
                }
            }

            self::setOut([
                'status' => $delete,
                'negativo' => $saldo_negativo,
                'data' => $inventario
            ]);

        } catch (\Exception $ex) {
            self::setOut([
                'status' => 'error',
                'message' => $ex->getMessage()
            ]);
        }

        return self::getOut();
    }

    public static function updateInventarioByTransferSaldo($idempleado, EgresoBodegaDetalle $detalle, $cantidad_saldar = 0)
    {
        try {
            $save = false;
            //Traemos los datos del calendario
            $calendario = self::calendario($detalle['fecha_salida']);

            $inventario = new InventarioEmpleado();
            $inventario->idcalendar = $calendario->codigo;
            $inventario->idempleado = $idempleado;
            $inventario->idmaterial = $detalle['idmaterial'];
            $inventario->sld_inicial = 0;
            $inventario->tot_egreso = $detalle['cantidad'];
            $inventario->tot_consumo = 0;
            $inventario->tot_devolucion = 0;
            $inventario->created_at = Carbon::now()->format(config('constants.format_date'));

            $existe = InventarioEmpleado::existeInventario($inventario);

            if (is_object($existe)) {
                $inventario = $existe;
                $inventario->tot_egreso = ($inventario->tot_egreso - $cantidad_saldar) + $detalle['cantidad'];
            } else {
                $inventario->tot_egreso = $detalle['cantidad'];
            }

            $inventario->saldoFinal();
            $inventario->updated_at = Carbon::now()->format(config('constants.format_date'));
            $save = $inventario->save();

            self::setOut([
                'status' => $save,
                'data' => $inventario
            ]);
        } catch (\Exception $ex) {
            self::setOut([
                'status' => false,
                'message' => $ex->getMessage()
            ]);
        }

        return self::getOut();
    }

    public static function calendario($fecha)
    {
        return Calendario::where('fecha', $fecha)->first();
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
