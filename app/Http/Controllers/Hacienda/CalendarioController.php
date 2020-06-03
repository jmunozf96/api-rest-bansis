<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Sistema\Calendario;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function semanaEnfunde(Request $request)
    {
        try {
            $fecha = $request->get('fecha');

            if ($fecha && !empty($fecha)) {
                $presente = strtotime(str_replace('/', '-', $fecha));
                $futuro = strtotime(str_replace('/', '-', $fecha . "+ 7 days"));
                $fecha_pre = date(config('constants.date'), $presente);
                $fecha_fut = date(config('constants.date'), $futuro);

                $calendario_pre = Calendario::where('fecha', $fecha_pre)->first();
                $calendario_fut = Calendario::where('fecha', $fecha_fut)->first();

                if (is_object($calendario_pre) && is_object($calendario_fut)) {
                    $semana = [
                        'presente' => $calendario_pre,
                        'futuro' => $calendario_fut
                    ];

                    $this->out = $this->respuesta_json('success', 200, 'Datos encontrados');
                    $this->out['calendario'] = $semana;
                    
                    return response()->json($this->out, 200);
                }

                throw new \Exception('No se han encontrado datos');

            }

            throw new \Exception('No se han recibido parametros de fecha');

        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
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
