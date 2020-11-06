<?php

namespace App\Http\Controllers\Sisban\Cosecha;

use App\Http\Controllers\Controller;
use App\Models\Hacienda\LoteSeccion;
use App\Models\Sisban\Cosecha\Danos;
use App\Models\Sistema\Calendario;
use App\Models\Sistema\ManosRecusadas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManosRecusadasController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'getDanos']]);
        $this->out = $this->respuesta_json('error', 500, 'Error en respuesta desde el servidor.');
    }

    public function index(Request $request, $hacienda)
    {
        try {
            $desde = $request->get('desde');
            $hasta = $request->get('hasta');
            $desde = strtotime(str_replace('/', '-', $desde));
            $hasta = strtotime(str_replace('/', '-', $hasta));

            if ($desde && $hasta) {
                $desde = date(config('constants.date'), $desde);
                $hasta = date(config('constants.date'), $hasta);
            } else {
                //Fecha actual
                $desde = date(config('constants.format_date'));
                $hasta = date(config('constants.format_date'));
            }

            $lotes = LoteSeccion::select('id', DB::raw("right('000' + ltrim(alias), 3) as alias"),
                'idlote', 'has', 'variedad', 'tipo_variedad', 'tipo_suelo', 'latitud', 'longitud')
                ->whereHas('lote', function ($query) use ($hacienda) {
                    $query->where('idhacienda', $hacienda);
                })
                ->with(['lote' => function ($query) {
                    $query->select('id', 'idhacienda');
                    $query->with(['hacienda' => function ($query) {

                    }]);
                }])
                ->whereHas('manosRecusadas', function ($query) use ($desde, $hasta) {
                    $query->where('mano', true);
                    $query->whereHas('calendario', function ($query) use ($desde, $hasta) {
                        $query->whereBetween('fecha', [$desde, $hasta]);
                    });
                })
                ->with(['manosRecusadas' => function ($query) use ($desde, $hasta) {
                    $query->select('idhacienda', 'idlote', 'dano_des',
                        DB::raw("sum(cantidad) as cantidad"),
                        'otros', 'otros_des');
                    $query->groupBy('idhacienda', 'idlote', 'dano_des', 'otros', 'otros_des');
                    $query->where('mano', true);
                    $query->pluck('cantidad', 'idhacienda', 'idlote', 'dano_des', 'otros', 'otros_des');
                    $query->with(['dano' => function ($query) {
                        $query->select('id', 'nombre', 'detalle');
                    }]);
                    $query->whereBetween('fecha', [$desde, $hasta]);
                }])
                ->get();
            if (count($lotes) > 0) {
                $mensaje = 'Datos del dia entonctrados';
            } else {
                $mensaje = 'No se encontraron datos';
            }
            $this->out = $this->respuesta_json('sucess', 200, $mensaje);
            $this->out['datos'] = $lotes;

        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function getDanos(Request $request, $hacienda)
    {
        try {
            $desde = $request->get('desde');
            $hasta = $request->get('hasta');
            $desde = strtotime(str_replace('/', '-', $desde));
            $hasta = strtotime(str_replace('/', '-', $hasta));

            if ($desde && $hasta) {
                $desde = date(config('constants.date'), $desde);
                $hasta = date(config('constants.date'), $hasta);
            } else {
                //Fecha actual
                $desde = date(config('constants.format_date'));
                $hasta = date(config('constants.format_date'));
            }

            $danos_fecha = ManosRecusadas::select('dano_des')
                ->whereBetween('fecha', [$desde, $hasta])
                ->where(['idhacienda' => $hacienda])
                ->with('dano')
                ->groupBy('dano_des')
                ->get()
                ->pluck('dano.id');

            $danos = Danos::all();
            $array_danos = [];
            foreach ($danos as $dano) {
                $data = clone $dano;
                $data->selected = false;
                $data->disabled = true;
                foreach ($danos_fecha as $dano_fecha) {
                    if ($data->id == $dano_fecha) {
                        $data->selected = true;
                        $data->disabled = false;
                        break;
                    }
                }
                array_push($array_danos, $data);
            }

            $this->out = $this->respuesta_json('sucess', 200, 'Daños en racimos.');
            $this->out['datos'] = $array_danos;
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
        }
        return response()->json($this->out, $this->out['code']);
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
