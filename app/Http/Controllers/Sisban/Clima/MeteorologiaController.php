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
                        $nw_horaSol = HoraSol::existe($hora_sol['fecha']);
                        if (!is_object($nw_horaSol)) {
                            $nw_horaSol = new HoraSol();
                            $nw_horaSol->fecha = $hora_sol['fecha'];
                            $nw_horaSol->total = $hora_sol['horas'];
                            $nw_horaSol->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_horaSol->updated_at = Carbon::now()->format(config('constants.format_date'));
                        } else {
                            $nw_horaSol->total = $hora_sol['horas'];
                            $nw_horaSol->updated_at = Carbon::now()->format(config('constants.format_date'));
                        }
                        $nw_horaSol->save();
                    }
                }

                //Micrometro
                $datas_micrometro = $params_array['Micrometro'];
                if (count($datas_micrometro) > 0) {
                    foreach ($datas_micrometro as $micrometro) {
                        $nw_micrometro = Micrometro::existe($micrometro['fecha']);
                        if (!is_object($nw_micrometro)) {
                            $nw_micrometro = new Micrometro();
                            $nw_micrometro->fecha = $micrometro['fecha'];
                            $nw_micrometro->total = $micrometro['total'];
                            $nw_micrometro->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_micrometro->updated_at = Carbon::now()->format(config('constants.format_date'));
                        } else {
                            $nw_micrometro->total = $micrometro['total'];
                            $nw_micrometro->updated_at = Carbon::now()->format(config('constants.format_date'));
                        }

                        $nw_micrometro->save();

                        //Modificar el dia anterior
                        $fecha_anterior = date('Y-m-d', strtotime($micrometro['fecha'] . " -1 days"));
                        $edit_micrometro = Micrometro::existe($fecha_anterior);
                        if (!empty($edit_micrometro)) {
                            $edit_micrometro->evaporacion = abs($micrometro['total'] - $edit_micrometro->total);
                            $edit_micrometro->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $edit_micrometro->update();
                        }
                    }
                }

                //Precipitacion
                $datas_precipitacion = $params_array['Precipitacion'];
                if (count($datas_precipitacion) > 0) {
                    foreach ($datas_precipitacion as $precipitacion) {
                        $nw_precipitacion = Precipitacion::existe($precipitacion['fecha'], $precipitacion['hacienda']['id']);
                        if (!is_object($nw_precipitacion)) {
                            $nw_precipitacion = new Precipitacion();
                            $nw_precipitacion->fecha = $precipitacion['fecha'];
                            $nw_precipitacion->idhacienda = $precipitacion['hacienda']['id'];
                            $nw_precipitacion->total = $precipitacion['total'];
                            $nw_precipitacion->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_precipitacion->updated_at = Carbon::now()->format(config('constants.format_date'));
                        } else {
                            $nw_precipitacion->total = $precipitacion['total'];
                            $nw_precipitacion->updated_at = Carbon::now()->format(config('constants.format_date'));
                        }
                        $nw_precipitacion->save();
                    }
                }

                //Temperatura
                $datas_temperatura = $params_array['Temperatura'];
                if (count($datas_temperatura) > 0) {
                    foreach ($datas_temperatura as $temperatura) {
                        $nw_temperatura = Temperatura::existe($temperatura['fecha']);
                        if (!is_object($nw_temperatura)) {
                            $nw_temperatura = new Temperatura();
                            $nw_temperatura->fecha = $temperatura['fecha'];
                            $nw_temperatura->tmin = $temperatura['tmin'];
                            $nw_temperatura->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_temperatura->updated_at = Carbon::now()->format(config('constants.format_date'));
                        } else {
                            $nw_temperatura->tmin = $temperatura['tmin'];
                            $nw_temperatura->updated_at = Carbon::now()->format(config('constants.format_date'));
                        }
                        $nw_temperatura->save();

                        $fecha_anterior = date('Y-m-d', strtotime($temperatura['fecha'] . " -1 days"));
                        $nw_temperatura = Temperatura::existe($fecha_anterior);
                        if (!is_object($nw_temperatura)) {
                            $nw_temperatura = new Temperatura();
                            $nw_temperatura->fecha = $fecha_anterior;
                            $nw_temperatura->tmax = $temperatura['tmax'];
                            $nw_temperatura->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_temperatura->updated_at = Carbon::now()->format(config('constants.format_date'));
                        } else {
                            $nw_temperatura->tmax = $temperatura['tmax'];
                            $nw_temperatura->updated_at = Carbon::now()->format(config('constants.format_date'));
                        }
                        $nw_temperatura->save();
                    }
                }

                //Termometro
                $datas_termometro = $params_array['Termometro'];
                if (count($datas_termometro) > 0) {
                    foreach ($datas_termometro as $termometro) {
                        $nw_termometro = Termometro::existe($termometro['fecha']);
                        if (!is_object($nw_termometro)) {
                            $nw_termometro = new Termometro();
                            $nw_termometro->fecha = $termometro['fecha'];
                            $nw_termometro->tseco = $termometro['seco'];
                            $nw_termometro->thumedo = $termometro['humedo'];
                            $nw_termometro->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_termometro->updated_at = Carbon::now()->format(config('constants.format_date'));
                        } else {
                            $nw_termometro->tseco = $termometro['seco'];
                            $nw_termometro->thumedo = $termometro['humedo'];
                            $nw_termometro->updated_at = Carbon::now()->format(config('constants.format_date'));
                        }
                        $nw_termometro->save();
                    }
                }

                //Viento
                $datas_viento = $params_array['Viento'];
                if (count($datas_viento) > 0) {
                    foreach ($datas_viento as $viento) {
                        $nw_viento = Viento::existe($viento['fecha']);
                        if (!is_object($nw_viento)) {
                            $nw_viento = new Viento();
                            $nw_viento->fecha = $viento['fecha'];
                            $nw_viento->total = $viento['km'];
                            $nw_viento->created_at = Carbon::now()->format(config('constants.format_date'));
                            $nw_viento->updated_at = Carbon::now()->format(config('constants.format_date'));
                            //return response()->json($nw_evaporacion, 200);
                        } else {
                            $nw_viento->total = $viento['km'];
                            $nw_viento->updated_at = Carbon::now()->format(config('constants.format_date'));
                        }
                        $nw_viento->save();

                        //Modificar el dia anterior
                        $fecha_anterior = date('Y-m-d', strtotime($viento['fecha'] . " -1 days"));
                        $edit_viento = Viento::existe($fecha_anterior);
                        if (!empty($edit_viento)) {
                            $edit_viento->kmhora = round(abs($viento['km'] - $edit_viento->total) / 24, 2);
                            $edit_viento->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $edit_viento->save();
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
