<?php

namespace App\Http\Controllers\Sisban;

use App\Events\CosechaPrimo;
use App\Events\CosechaSofca;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Hacienda\LoteSeccion;
use App\Models\Sisban\HelperCosecha;
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
            'statusCosecha', 'getCosecha', 'executeEventBalanzaPrimo', 'executeEventBalanzaSofca',
            'getCintasSemana', 'getCosechaLote', 'getLotesCortadosDia',
            'getCajasDia', 'getLotesRecobro', 'getCintaRecobro']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');

        $this->api_balanza = "http://ingreatsol.com/appbalanza/api/web/index.php/caja/listreport/";
        $this->token_balanza = "access-token=d8f1a9e7-7697-4f92-b07b-20ad743f9112";
        $this->help = new Helper();
    }

    public function index()
    {
        //
    }

    public function executeEventBalanzaPrimo()
    {
        $cosecha = DB::connection('SISBAN')->table('cosecha_primo_temp')
            ->orderBy('cs_id', 'desc')
            ->take(1)
            ->lock('WITH(NOLOCK)')
            ->first();

        //Luego la aliminas
        event(new CosechaPrimo($cosecha));
    }

    public function executeEventBalanzaSofca()
    {
        $cosecha = DB::connection('SISBAN')->table('cosecha_sofca_temp')
            ->orderBy('cs_id', 'desc')
            ->take(1)
            ->lock('WITH(NOLOCK)')
            ->first();

        //Luego la aliminas
        event(new CosechaSofca($cosecha));
    }

    public function cosechaHacienda($hacienda)
    {
        $cosecha = null;
        if ($hacienda == 1) {
            $cosecha = DB::connection('SISBAN')->table('cosecha_primo');
        } else {
            $cosecha = DB::connection('SISBAN')->table('cosecha_sofca');
        }
        return $cosecha;
    }

    public function enfundeCintaHacienda($hacienda)
    {
        $enfunde = null;
        if ($hacienda == 1) {
            $enfunde = DB::connection('SISBAN')->table('primo.saldo_cinta_lote');
        } else {
            $enfunde = DB::connection('SISBAN')->table('sofca.saldo_cinta_lote');
        }
        return $enfunde;
    }

    public function perdidasCinta($hacienda)
    {
        $perdidas = null;
        if ($hacienda == 1) {
            $perdidas = DB::connection('SISBAN')->table('perdidas_primo');
        } else {
            $perdidas = DB::connection('SISBAN')->table('perdidas_sofca');
        }
        return $perdidas;
    }

    public function loadingData(Request $request)
    {
        try {
            $json = $request->input('json', null);
            $params_array = json_decode($json, true);
            if (!empty($params_array)) {
                $this->out = $this->respuesta_json('success', 200, "Datos generados correctamente.");
                $this->out['data'] = $params_array;
                $this->out['cintas'] = $this->getCintasSemana($params_array['hacienda'], $params_array['semanas'], $params_array['fecha']);
                $this->out['cosecha'] = $this->getCosechaDia($params_array['hacienda'], $params_array['fecha']);

                return response()->json($this->out, 200);
            }

            throw new \Exception('No se han enviado parametros');
        } catch (\Exception $ex) {
            $this->out['error'] = $ex->getMessage();
            return response()->json($this->out, 500);
        }
    }

    public function getCintasSemana($hacienda, $cintas, $fecha)
    {
        $hacienda = $hacienda == 3 ? 2 : 1;
        $fecha = strtotime(str_replace('/', '-', $fecha));
        $fecha = date(config('constants.date'), $fecha);
        $cintas_semana = array();

        if (!empty($fecha)) {
            //Creamos datos temporales
            HelperCosecha::tabla_temporal_data_cintas_drop();
            HelperCosecha::tabla_temporal_data_cintas();

            foreach ($cintas as $cinta) {
                $dias = (($cinta['value'] - 1) * 7);
                $fecha_nw = date("d - m - Y", strtotime($fecha . " - $dias days"));

                $data = DB::connection('SISBAN')->table('calendario_dole')
                    ->select(DB::raw("semanaCorte = " . $cinta['value']), 'idcalendar',
                        'color', 'semana as SemanaEnfunde')
                    ->where("fecha", $fecha_nw)
                    ->orderBy('semanaCorte', 'desc')
                    ->first();

                $data_cinta = $this->cosechaHacienda($hacienda)
                    ->where([
                        'cs_color' => $data->idcalendar
                    ])->lock('WITH(NOLOCK)');

                $bindings = $data_cinta->getBindings();
                $insertQuery = 'INSERT into cosecha_cintas ' . $data_cinta->toSql();
                DB::connection('SISBAN')->insert($insertQuery, $bindings);

                $_cinta = new \stdClass();
                $_cinta->recobro = $this->getCintaRecobro($hacienda, $fecha, $data->idcalendar);
                $_cinta->data = $this->getLotesRecobro($hacienda, $fecha, $data->idcalendar);
                array_push($cintas_semana, $_cinta);
            }

            HelperCosecha::tabla_temporal_data_cintas_drop();
        }
        return $cintas_semana;
    }

    public function getCintaRecobro($hacienda, $fecha, $cinta)
    {
        $recobro = array();

        if (!empty($cinta)) {
            $enfunde_cinta = $this->enfundeCintaHacienda($hacienda)
                ->select('en_color', DB::raw('SUM(en_cantpre + en_cantfut) as total'))
                ->where('en_color', $cinta)
                ->groupBy('en_color')->first();

            $racimos_cortados = DB::connection('SISBAN')->table('cosecha_cintas')
                ->select(DB::raw('COUNT(cs_peso) as total'))
                ->where('cs_color', $cinta)
                ->where('cs_haciend', $hacienda)
                ->where('cs_fecha', '<>', $fecha)
                ->lock('WITH(NOLOCK)')
                ->first();

            $perdidas = $this->perdidasCinta($hacienda)->select(DB::raw("SUM(pe_cant) as total"))
                ->where('pe_color', $cinta)
                ->first();

            $cinta = DB::connection('SISBAN')->table('calendario_dole')
                ->select('color', 'idcalendar')
                ->where('idcalendar', $cinta)
                ->first();

            $recobro = [
                'codigo' => $cinta->idcalendar,
                'color' => $cinta->color,
                'enfunde' => $enfunde_cinta->total,
                'caidas' => $perdidas->total,
                'cortados' => $racimos_cortados->total,
                'recobro' => (1 - ($racimos_cortados->total / $enfunde_cinta->total)) * 100
            ];
        }
        return $recobro;
    }

    public function getLotesRecobro($hacienda, $fecha, $cinta)
    {
        $hacienda_web = $hacienda === 2 ? 3 : 1;
        $fecha = strtotime(str_replace('/', '-', $fecha));
        $fecha = date(config('constants.date'), $fecha);

        $lotes = LoteSeccion::join('HAC_LOTES as lote', 'lote.id', 'HAC_LOTES_SECCION.idlote')
            ->select('HAC_LOTES_SECCION.id', 'HAC_LOTES_SECCION.has', 'HAC_LOTES_SECCION.variedad',
                'HAC_LOTES_SECCION.latitud', 'HAC_LOTES_SECCION.longitud',
                DB::raw("(right('0' + lote.identificacion,2) + HAC_LOTES_SECCION.descripcion) as descripcion"))
            ->whereHas('lote', function ($query) use ($hacienda_web) {
                $query->where('idhacienda', $hacienda_web);
            })
            ->where('HAC_LOTES_SECCION.estado', true)
            ->orderByRaw("(right('0' + lote.identificacion,2) + HAC_LOTES_SECCION.descripcion)")
            ->get();

        $series = array();
        $cortados = array();
        $saldos = array();
        $labels = array();
        $data = array();

        if (count($lotes) > 0) {
            foreach ($lotes as $lote):
                $enfunde = $this->enfundeCintaHacienda($hacienda)
                    ->where([
                        ['en_seccion', 'like', '%' . $lote->descripcion . '%'],
                        'en_color' => $cinta
                    ])
                    ->get()->sum(function ($query) {
                        return $query->en_cantpre + $query->en_cantfut;
                    });

                //Tabla temporal
                $cortado = DB::connection('SISBAN')->table('cosecha_cintas')->where([
                    ['cs_seccion', 'like', '%' . $lote->descripcion . '%'],
                    'cs_color' => $cinta,
                    'cs_haciend' => $hacienda
                ])->lock('WITH(NOLOCK)')->get()->count();

                $cortado_antes_fecha = DB::connection('SISBAN')->table('cosecha_cintas')->where([
                    ['cs_seccion', 'like', '%' . $lote->descripcion . '%'],
                    ['cs_seccion', '<>', $fecha],
                    'cs_color' => $cinta,
                    'cs_haciend' => $hacienda
                ])->lock('WITH(NOLOCK)')->get()->count();

                $perdidas = $this->perdidasCinta($hacienda)
                    ->where('pe_color', $cinta)
                    ->where('pe_seccion', $lote->descripcion)
                    ->get()->sum(function ($query) {
                        return $query->pe_cant;
                    });

                array_push($data, (object)[
                    'lote' => $lote->descripcion,
                    'caidas' => $perdidas,
                    'enfunde' => $enfunde,
                    'cortado' => $cortado_antes_fecha,
                    'coordenadas' => [
                        'latitud' => $lote->latitud,
                        'longitud' => $lote->longitud
                    ]
                ]);

                array_push($cortados, $cortado + $perdidas);
                array_push($series, $enfunde);
                array_push($saldos, ($enfunde - ($cortado + $perdidas)) >= 0 ? ($enfunde - ($cortado + $perdidas)) : 0);
                array_push($labels, $lote->descripcion);
                $lote->enfunde = $enfunde;
                $lotes->cortados = 0;
            endforeach;
        }

        return [
            'lotes' => $data,
            'chart' => [
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
                'saldos' => [
                    'name' => 'Saldo',
                    'type' => 'column',
                    'data' => $saldos
                ],
                'categories' => $labels
            ]
        ];
    }

    public function getCosechaDia($hacienda, $fecha)
    {
        $fecha = strtotime(str_replace('/', '-', $fecha));
        $fecha = date(config('constants.date'), $fecha);

        $tabla = $hacienda === 1 ? 'cosecha_primo_temp' : 'cosecha_sofca_temp';

        return DB::connection('SISBAN')->table($tabla)
            ->select(DB::raw("max(cs_id) as cs_id"), 'cs_fecha', 'cs_haciend', 'cs_seccion',
                DB::raw("count(*) as cs_cortados"),
                DB::raw("sum(cs_peso) as cs_peso"),
                'cs_color', DB::raw("max(fechacre) as ultima_actualizacion"))
            ->where('cs_fecha', $fecha)
            ->groupBy('cs_fecha', 'cs_haciend', 'cs_seccion', 'cs_color')
            ->orderBy(DB::raw("max(fechacre)"), 'desc')
            ->get();
    }

    public function getCajasDia($hacienda, Request $request)
    {
        try {
            $hacienda = $hacienda == 3 ? '56' : '55';
            $fecha = $request->get('fecha');
            $fecha = strtotime(str_replace('/', '-', $fecha));
            $desde = date("Y-m-d", $fecha);
            $hasta = date("Y-m-d", strtotime("+1 day", $fecha));

            $cajas = file_get_contents($this->api_balanza . "$hacienda?datefrom=$desde&dateuntil=$hasta&" . $this->token_balanza);
            $data = json_decode($cajas, true);
            $data_cajas = array();

            if (count($data) > 0) {
                $cajas = array_column($data, 'idcaja');
                $cajas_only = array_unique($cajas);

                if (count($cajas) > count($cajas_only)) {
                    //Hay cajas repetidas
                    foreach ($cajas_only as $item) {
                        $datos_Caja = DB::connection('SISBAN')->table('CAJ_CAJAS_BANANERA')->where([
                            'id_refBalanza' => $item
                        ])->first();

                        $data_nw = array_filter($data, function ($data) use ($item) {
                            return $data['idcaja'] == $item;
                        });

                        $last_pesada = array_reduce($data_nw, function ($A, $B) {
                            return $A['last'] > $B['last'] ? $A : $B;
                        }, array_shift($data_nw));

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
                        $caja->last = $last_pesada['last'];

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
                        $caja->last = $item->last;

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

    public function respuesta_json(...$datos)
    {
        return array(
            'status' => $datos[0],
            'code' => $datos[1],
            'message' => $datos[2]
        );
    }
}
