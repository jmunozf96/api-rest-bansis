<?php

namespace App\Http\Controllers\Sisban\Primo;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Hacienda\LoteSeccion;
use App\Models\Sisban\Primo\Cosecha;
use App\Models\Sistema\Calendario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CosechaController extends Controller
{
    protected $out;
    protected $api_balanza;
    protected $token_balanza;
    protected $help;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show',
            'statusCosecha', 'getCosecha',
            'getCintasSemana', 'getCosechaLote', 'getLotesCortadosDia', 'getCajasDia', 'getLotesRecobro']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');

        $this->api_balanza = "http://ingreatsol.com/appbalanza/api/web/index.php/caja/listreport/";
        $this->token_balanza = "access-token=d8f1a9e7-7697-4f92-b07b-20ad743f9112";
        $this->help = new Helper();
    }

    public function index()
    {
        //
    }


    public function store(Request $request)
    {
        //
    }


    public function show($id)
    {
        //
    }


    public function update(Request $request, $id)
    {
        //
    }


    public function destroy($id)
    {
        //
    }

    public function statusCosecha(Request $request)
    {
        try {
            $fecha = $request->get('fecha');
            $fecha = strtotime(str_replace('/', '-', $fecha));
            $fecha = date(config('constants.date'), $fecha);

            $cosecha = Cosecha::where(['cs_fecha' => $fecha])->count();
            $this->out = $this->respuesta_json('success', 200, 'Contador Activo');
            $this->out['contador'] = $cosecha;
            return response()->json($this->out, 200);
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function getCosecha(Request $request)
    {
        try {
            $fecha = $request->get('fecha');
            $fecha = strtotime(str_replace('/', '-', $fecha));
            $fecha = date(config('constants.date'), $fecha);

            if (!empty($fecha) && !is_null($fecha)) {
                $cosecha = Cosecha::select('cs_fecha', 'cs_seccion', 'cs_peso', 'fechacre', 'cs_color', 'calendario.color')
                    ->join('calendario_dole as calendario', 'calendario.idcalendar', 'cs_color')
                    ->where('cs_fecha', 'like', '%' . $fecha . '%')->orderBy('fechacre', 'desc')->take(1)->first();
                if (is_object($cosecha)) {
                    $enfunde_cinta = DB::connection('SISBAN')->table('primo.saldo_cinta_lote')
                        ->select('en_color', DB::raw('SUM(en_cantpre + en_cantfut) as total'))
                        ->where('en_color', $cosecha['cs_color'])
                        ->groupBy('en_color')->first();

                    $racimos_cortados = Cosecha::select(DB::raw('COUNT(cs_peso) as total'))
                        ->where('cs_color', $cosecha['cs_color'])
                        ->first();


                    return response()->json([
                        'code' => 200,
                        'cosecha' => $cosecha,
                        'recobro' => [
                            'enfunde' => $enfunde_cinta->total,
                            'cortados' => $racimos_cortados->total,
                            'recobro' => (1 - ($racimos_cortados->total / $enfunde_cinta->total)) * 100
                        ],
                    ], 200);
                }

                throw new \Exception('No se encontro datos');
            }
            throw new \Exception('No se ha enviado parametro de fecha');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function getCosechaLote(Request $request)
    {
        try {
            $color = $request->get('color');
            $lote = $request->get('lote');

            if (!empty($color) && !is_null($color)) {
                if (!empty($lote) && !is_null($lote)) {
                    $enfunde_cinta = DB::connection('SISBAN')->table('primo.saldo_cinta_lote')
                        ->select('en_color', DB::raw('SUM(en_cantpre + en_cantfut) as total'))
                        ->where('en_color', $color)
                        ->where('en_seccion', $lote)
                        ->groupBy('en_color')
                        ->first();

                    $racimos_cortados = Cosecha::where('cs_color', $color)
                        ->where('cs_seccion', 'like', "%$lote%")
                        ->select(DB::raw('COUNT(*) as total'), DB::raw('SUM(cs_peso) as peso'))
                        ->first();

                    return response()->json([
                        'code' => 200,
                        'datos' => [
                            'enfunde' => $enfunde_cinta->total,
                            'cortados' => $racimos_cortados->total,
                            'peso' => $racimos_cortados->peso,
                            'recobro' => (1 - ($racimos_cortados->total / $enfunde_cinta->total)) * 100
                        ],
                    ], 200);
                }

                //throw new \Exception('No se encontro datos');
                throw new \Exception('No se ha enviado parametro de lote');
            }
            throw new \Exception('No se ha enviado parametro de color');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function getLotesCortadosDia(Request $request)
    {
        try {
            $color = $request->get('color');
            $fecha = $request->get('fecha');
            $lote = $request->get('lote');

            if (!empty($color) && !is_null($color)) {
                $lotes = Cosecha::groupBy('cs_seccion', 'cs_fecha', 'cs_color')
                    ->where('cs_color', $color);

                if (!empty($fecha) && !is_null($fecha)) {
                    $fecha = strtotime(str_replace('/', '-', $fecha));
                    $fecha = date(config('constants.date'), $fecha);
                    $lotes = $lotes->where('cs_fecha', $fecha);
                }

                if (!empty($lote) && !is_null($lote)) {
                    $lotes = $lotes->where('cs_seccion', $lote);
                }

                $lotes = $lotes->select('cs_seccion', 'cs_color',
                    DB::raw("(select sum(en_cantpre + en_cantfut) from primo.saldo_cinta_lote where en_seccion = cs_seccion and en_color = cs_color) as enfunde"),
                    DB::raw("(select top 1 color from calendario_dole where idcalendar = cs_color) as color"),
                    DB::raw("(select sum(cs_peso) from cosecha_primo primo with(nolock) where primo.cs_seccion = cosecha_primo.cs_seccion and primo.cs_color = cosecha_primo.cs_color) as peso"),
                    DB::raw("(select count(cs_peso) from cosecha_primo primo with(nolock) where primo.cs_seccion = cosecha_primo.cs_seccion and primo.cs_color = cosecha_primo.cs_color) as cortados, activo = 0")
                )->get();

                return response()->json([
                    'code' => 200,
                    'data' => $lotes,
                ], 200);
            }
            throw new \Exception('No se ha enviado parametro de color');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function getCintasSemana(Request $request)
    {
        try {
            $fecha = $request->get('fecha');
            $fecha = strtotime(str_replace('/', '-', $fecha));
            $fecha = date(config('constants.date'), $fecha);

            if (!empty($fecha) && !is_null($fecha)) {
                $dias = (12 * 7);
                $fecha13 = date("d - m - Y", strtotime($fecha . " - $dias days"));
                $cintas13Semanas = DB::connection('SISBAN')->table('calendario_dole')
                    ->select(DB::raw('semanaCorte = 13'), 'idcalendar as codigo', 'color', 'semana as SemanaEnfunde')
                    ->where("fecha", $fecha13);

                $dias = (11 * 7);
                $fecha12 = date("d - m - Y", strtotime($fecha . " - $dias days"));
                $cintas12Semanas = DB::connection('SISBAN')->table('calendario_dole')
                    ->select(DB::raw('semanaCorte = 12'), 'idcalendar', 'color', 'semana as SemanaEnfunde')
                    ->where("fecha", $fecha12)->unionAll($cintas13Semanas);

                $dias = (10 * 7);
                $fecha11 = date("d - m - Y", strtotime($fecha . " - $dias days"));
                $cintas11Semanas = DB::connection('SISBAN')->table('calendario_dole')
                    ->select(DB::raw('semanaCorte = 11'), 'idcalendar', 'color', 'semana as SemanaEnfunde')
                    ->where("fecha", $fecha11)->unionAll($cintas12Semanas);

                $dias = (9 * 7);
                $fecha10 = date("d - m - Y", strtotime($fecha . " - $dias days"));
                $cintas10Semanas = DB::connection('SISBAN')->table('calendario_dole')
                    ->select(DB::raw('semanaCorte = 10'), 'idcalendar', 'color', 'semana as SemanaEnfunde')
                    ->where("fecha", $fecha10)->unionAll($cintas11Semanas);

                $dias = (8 * 7);
                $fecha10 = date("d - m - Y", strtotime($fecha . " - $dias days"));
                $cintas09Semanas = DB::connection('SISBAN')->table('calendario_dole')
                    ->select(DB::raw('semanaCorte = 9'), 'idcalendar', 'color', 'semana as SemanaEnfunde')
                    ->where("fecha", $fecha10)->unionAll($cintas10Semanas)
                    ->orderBy('semanaCorte', 'desc')
                    ->get();

                $cintasSemana = $cintas09Semanas;

                return response()->json([
                    'code' => 200,
                    'cintas' => $cintasSemana
                ], 200);
            }
            throw new \Exception('No se ha enviado parametro de fecha');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function getCajasDia(Request $request)
    {
        try {
            $fecha = $request->get('fecha');
            $fecha = strtotime(str_replace('/', '-', $fecha));
            $desde = date("Y-m-d", $fecha);
            $hasta = date("Y-m-d", strtotime("+1 day", $fecha));

            $cajas = file_get_contents($this->api_balanza . "55?datefrom=$desde&dateuntil=$hasta&" . $this->token_balanza);
            $data = json_decode($cajas, true);
            $data_cajas = array();

            if (count($data) > 0) {
                $cajas = array_column($data, 'idcaja');
                $cajas_only = array_unique($cajas);

                if (count($cajas) > count(array_unique($cajas))) {
                    //Hay cajas repetidas
                    foreach ($cajas_only as $item) {
                        $datos_Caja = DB::connection('SISBAN')->table('CAJ_CAJAS_BANANERA')->where([
                            'id_refBalanza' => $item
                        ])->first();

                        $data_nw = array_filter($data, function ($data) use ($item) {
                            return $data['idcaja'] == $item;
                        });

                        $total_pesadas = array_reduce($data_nw, function ($total, $item) {
                            $total += $item['totalpesadas'];
                            return $total;
                        }, 0);

                        $total_cajas = array_reduce($data_nw, function ($total, $item) {
                            $total += $item['totaldecajas'];
                            return $total;
                        }, 0);

                        $total_peso = array_reduce($data_nw, function ($total, $item) {
                            $total += $item['pesototal'];
                            return $total;
                        }, 0);

                        $caja = new \stdClass();
                        $caja->idcaja = $item;
                        $caja->totalpesadas = $total_pesadas;
                        $caja->totalcajas = $total_cajas;
                        $caja->pesototal = $total_peso;
                        $caja->datos = [
                            'id' => $datos_Caja->id_cj,
                            'codigo' => $datos_Caja->cod_cj,
                            'descripcion' => $datos_Caja->des_cj
                        ];

                        array_push($data_cajas, $caja);
                        //$item['total'] = $total_cajas;
                    }
                } else {
                    //No hay cajas repetidas
                    foreach ($data as $item) {
                        $item = (object)$item;

                        $datos_Caja = DB::connection('SISBAN')->table('CAJ_CAJAS_BANANERA')->where([
                            'id_refBalanza' => $item->idcaja
                        ])->first();

                        $caja = new \stdClass();
                        $caja->idcaja = $item->idcaja;
                        $caja->totalpesadas = $item->totalpesadas;
                        $caja->totalcajas = $item->totaldecajas;
                        $caja->pesototal = $item->pesototal;
                        $caja->datos = [
                            'id' => $datos_Caja->id_cj,
                            'codigo' => $datos_Caja->cod_cj,
                            'descripcion' => $datos_Caja->des_cj
                        ];

                        array_push($data_cajas, $caja);
                        //$item['total'] = $total_cajas;
                    }
                }
                $this->out = $this->respuesta_json('success', 200, 'Datos de caja con fecha actual, encontrados!');
                $this->out['data'] = $data_cajas;
                return response()->json($this->out, 200);
            }

            throw new \Exception('No se encontraron registros de caja para etsa fecha!');
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function getLotesRecobro(Request $request)
    {
        try {
            $cinta = $request->get('cinta');
            $lotes = LoteSeccion::join('HAC_LOTES as lote', 'lote.id', 'HAC_LOTES_SECCION.idlote')
                ->select('HAC_LOTES_SECCION.id', 'HAC_LOTES_SECCION.has', 'HAC_LOTES_SECCION.variedad',
                    DB::raw("(right('0' + lote.identificacion,2) + HAC_LOTES_SECCION.descripcion) as descripcion"))
                ->whereHas('lote', function ($query) {
                    $query->where('idhacienda', 1);
                })
                ->where('HAC_LOTES_SECCION.estado', true)
                ->orderByRaw("(right('0' + lote.identificacion,2) + HAC_LOTES_SECCION.descripcion)")
                ->get();
            if (count($lotes) > 0) {
                $cinta_color = DB::connection('SISBAN')->table('calendario_dole')->select('color')
                    ->where('idcalendar', $cinta)
                    ->first();

                $series = array();
                $cortados = array();
                $labels = array();
                $colors = array(); //#F0F043
                foreach ($lotes as $lote):
                    $enfunde = DB::connection('SISBAN')->table('primo.saldo_cinta_lote')
                        ->where([
                            ['en_seccion', 'like', '%' . $lote->descripcion . '%'],
                            'en_color' => $cinta
                        ])
                        ->get()->sum(function ($query) {
                            return $query->en_cantpre + $query->en_cantfut;
                        });

                    $cortado = Cosecha::where([
                        ['cs_seccion', 'like', '%' . $lote->descripcion . '%'],
                        'cs_color' => $cinta
                    ])->lock('WITH(NOLOCK)')->get()->count('cs_peso');

                    $recobro = ($enfunde - $cortado) >= 0 ? ($enfunde - $cortado) : 0;
                    array_push($cortados, $recobro);
                    array_push($series, $enfunde);
                    array_push($labels, $lote->descripcion);
                    //array_push($colors, ["#008ffb", $this->help->getColorHexadecimal(strtolower($cinta_color->color))]);
                    //array_push($colors, "#008ffb");
                    $lote->enfunde = $enfunde;
                    $lotes->cortados = 0;
                endforeach;
                return response()->json([
                    'enfunde' => [
                        'name' => 'Enfunde',
                        'type' => 'line',
                        'data' => $series
                    ],
                    'cortados' => [
                        'name' => 'Recobro',
                        'type' => 'column',
                        'data' => $cortados
                    ],
                    'categories' => $labels,
                    'cinta' => $colors
                ], 200);
            }
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
            return response()->json($this->out, 500);
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
