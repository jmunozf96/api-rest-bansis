<?php

namespace App\Http\Controllers\Sisban\Clima;

use App\Http\Controllers\Controller;
use App\Models\Sisban\Clima\Evaporacion;
use App\Models\Sisban\Clima\HoraSol;
use App\Models\Sisban\Clima\Micrometro;
use App\Models\Sisban\Clima\Precipitacion;
use App\Models\Sisban\Clima\Temperatura;
use App\Models\Sisban\Clima\Termometro;
use App\Models\Sisban\Clima\Viento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MeteorologiaController extends Controller
{

    public function __construct()
    {
        //$this->middleware('api.auth', ['except' => ['']]);
    }

    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        try {
            $json = $request->input('json', null);
            $params_array = json_decode($json, true);

            if (count($params_array) > 0) {
                DB::beginTransaction();
                //Horas Sol
               $datas_horaSol = $params_array['HoraSol'];
                if (count($datas_horaSol) > 0) {
                    foreach ($datas_horaSol as $hora_sol) {
                        $fecha = date('Y-m-d', strtotime($hora_sol['fecha']));
                        $nw_horaSol = HoraSol::where(['fecha' => $fecha])->first();
                        if (!is_object($nw_horaSol)) {
                            $nw_horaSol = new HoraSol();
                            $nw_horaSol->fecha = $fecha;
                            $nw_horaSol->total = $hora_sol['horas'];
                            $nw_horaSol->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_horaSol->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_horaSol->save();
                        } else {
                            $nw_horaSol->total = $hora_sol['horas'];
                            $nw_horaSol->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_horaSol->update();
                        }
                    }
                }


                //Precipitacion
                $datas_precipitacion = $params_array['Precipitacion'];
                if (count($datas_precipitacion) > 0) {
                    foreach ($datas_precipitacion as $precipitacion) {
                        $fecha = date('Y-m-d', strtotime($precipitacion['fecha']));
                        $nw_precipitacion = Precipitacion::where(['fecha' => $fecha, 'idhacienda' => $precipitacion['hacienda']['id']])->first();
                        if ($nw_precipitacion == null) {
                            $nw_precipitacion = new Precipitacion();
                            $nw_precipitacion->fecha = $fecha;
                            $nw_precipitacion->idhacienda = $precipitacion['hacienda']['id'];
                            $nw_precipitacion->total = $precipitacion['total'];
                            $nw_precipitacion->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_precipitacion->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_precipitacion->save();
                        } else {
                            $nw_precipitacion->total = $precipitacion['total'];
                            $nw_precipitacion->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_precipitacion->update();
                        }
                    }
                }

                //Micrometro
                $datas_micrometro = $params_array['Micrometro'];
                if (count($datas_micrometro) > 0) {
                    foreach ($datas_micrometro as $micrometro) {
                        $fecha = date('Y-m-d', strtotime($micrometro['fecha']));
                        $nw_micrometro = Micrometro::where(['fecha' => $fecha])->first();
                        if ($nw_micrometro == null) {
                            $nw_micrometro = new Micrometro();
                            $nw_micrometro->fecha = $fecha;
                            $nw_micrometro->total = $micrometro['total'];
                            $nw_micrometro->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_micrometro->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_micrometro->save();
                        } else {
                            $nw_micrometro->total = $micrometro['total'];
                            $nw_micrometro->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_micrometro->update();
                        }

                        //Modificar el dia anterior
                        $fecha_anterior = date('Y-m-d', strtotime($micrometro['fecha'] . " -1 days"));
                        $edit_micrometro = Micrometro::where(['fecha' => $fecha_anterior])->first();

                        if ($edit_micrometro !== null) {
                            $precipitacion_mm = 0;

                            if ($micrometro['calcPrecipitacion'] == 1) {
                                //Por lo general se escoge la precipitacion de la Hacienda Primo - codigo 1
                                $precipitacion = Precipitacion::where(['fecha' => $fecha_anterior, 'idhacienda' => $micrometro['haciendaPrecipitacion']['id']])->first();
                                if ($precipitacion) {
                                    $precipitacion_mm = $precipitacion->total;
                                }
                            }

                            if ($micrometro['enrase'] == 1) {
                                $edit_micrometro->evaporacion = abs($micrometro['total'] - ($micrometro['tenrase'] + $precipitacion_mm));
                            } else {
                                $edit_micrometro->evaporacion = abs($micrometro['total'] - ($edit_micrometro->total + $precipitacion_mm));
                            }

                            $edit_micrometro->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $edit_micrometro->update();
                        }
                    }
                }

                //Temperatura
                $datas_temperatura = $params_array['Temperatura'];
                if (count($datas_temperatura) > 0) {
                    foreach ($datas_temperatura as $temperatura) {
                        $fecha = date('Y-m-d', strtotime($temperatura['fecha']));
                        $nw_temperatura = Temperatura::where(['fecha' => $fecha])->first();
                        if ($nw_temperatura == null) {
                            $nw_temperatura = new Temperatura();
                            $nw_temperatura->fecha = $fecha;
                            $nw_temperatura->tmin = $temperatura['tmin'];
                            $nw_temperatura->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_temperatura->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_temperatura->save();
                        } else {
                            $nw_temperatura->tmin = $temperatura['tmin'];
                            $nw_temperatura->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_temperatura->update();
                        }

                        $fecha_anterior = date('Y-m-d', strtotime($temperatura['fecha'] . " -1 days"));
                        $nw_temperatura = Temperatura::where(['fecha' => $fecha_anterior])->first();
                        if ($nw_temperatura == null) {
                            $nw_temperatura = new Temperatura();
                            $nw_temperatura->fecha = $fecha_anterior;
                            $nw_temperatura->tmax = $temperatura['tmax'];
                            $nw_temperatura->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_temperatura->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_temperatura->save();
                        } else {
                            $nw_temperatura->tmax = $temperatura['tmax'];
                            $nw_temperatura->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_temperatura->update();
                        }
                    }
                }

                //Termometro
                $datas_termometro = $params_array['Termometro'];
                if (count($datas_termometro) > 0) {
                    foreach ($datas_termometro as $termometro) {
                        $fecha = date('Y-m-d', strtotime($termometro['fecha']));
                        $nw_termometro = Termometro::where(['fecha' => $fecha])->first();
                        if ($nw_termometro == null) {
                            $nw_termometro = new Termometro();
                            $nw_termometro->fecha = $fecha;
                            $nw_termometro->tseco = $termometro['seco'];
                            $nw_termometro->thumedo = $termometro['humedo'];
                            $nw_termometro->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_termometro->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_termometro->save();
                        } else {
                            $nw_termometro->tseco = $termometro['seco'];
                            $nw_termometro->thumedo = $termometro['humedo'];
                            $nw_termometro->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_termometro->update();
                        }
                    }
                }

                //Viento
                $datas_viento = $params_array['Viento'];
                if (count($datas_viento) > 0) {
                    foreach ($datas_viento as $viento) {
                        $fecha = date('Y-m-d', strtotime($viento['fecha']));
                        $nw_viento = Viento::where(['fecha' => $fecha])->first();
                        if ($nw_viento == null) {
                            $nw_viento = new Viento();
                            $nw_viento->fecha = $fecha;
                            $nw_viento->total = $viento['km'];
                            $nw_viento->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_viento->updated_at = Carbon::now()->format(config('constants.format_date'));
                            //return response()->json($nw_evaporacion, 200);
                            $nw_viento->save();
                        } else {
                            $nw_viento->total = $viento['km'];
                            $nw_viento->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_viento->update();
                        }

                        //Modificar el dia anterior
                        $fecha_anterior = date('Y-m-d', strtotime($viento['fecha'] . " -1 days"));
                        $edit_viento = Viento::where(['fecha' => $fecha_anterior])->first();
                        if ($edit_viento !== null) {
                            $edit_viento->kmhora = round(abs($viento['km'] - $edit_viento->total) / 24, 2);
                            $edit_viento->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $edit_viento->update();
                        }
                    }
                }

                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'code' => 200,
                    'message' => "Registros guardados correctamente!!!"
                ], 200);
            }
        } catch (\Exception $error) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => $error->getMessage() . ", error en linea: " . $error->getLine()
            ], 500);
        }
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
}
