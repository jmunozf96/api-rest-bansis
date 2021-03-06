<?php

namespace App\Http\Controllers\Empacadora;

use App\Helpers\JwtAuth;
use App\Http\Controllers\Controller;
use App\Models\Empacadora\EMP_CAJA;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmpCajaController extends Controller
{

    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        $cajas = EMP_CAJA::all()->load(['destino', 'distribuidor', 'tipo_caja', 'cod_coorporativo']);

        if (!is_null($cajas) && !empty($cajas) && count($cajas) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['cajas'] = $cajas;
        } else {
            $this->out['message'] = 'No hay datos registrados';
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function store(Request $request)
    {
        $json = $request->input('json', null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if (!empty($params_array) && count($params_array) > 0) {
            $validacion = Validator::make($params_array,
                [
                    'descripcion' =>
                        "required|unique:EMP_CAJAS,descripcion,NULL,NULL,id_destino,$params->id_destino,id_tipoCaja,$params->id_tipoCaja,id_distrib,$params->id_distrib",
                    'peso_max' => 'required|between:0,99.99',
                    'peso_min' => 'required|between:0,99.99',
                    'peso_standard' => 'required|between:0,99.99',
                    'id_destino' => ['required', 'exists:EMP_DESTINO,id'],
                    'id_tipoCaja' => ['required', 'exists:EMP_TIPO_CAJA,id'],
                    'id_distrib' => ['required', 'exists:EMP_DISTRIBUIDOR,id'],
                    'id_codAllweights' => 'between:0,9999999'
                ],
                [
                    'descripcion.unique' => 'La caja ya se encuentra registrada'
                ]);

            if ($validacion->fails()) {
                $this->out['message'] = "Los datos enviados no son correctos";
                $this->out['error'] = $validacion->errors();
            } else {
                $caja = new EMP_CAJA();
                $caja->descripcion = $params_array['descripcion'];
                $caja->peso_max = $params_array['peso_max'];
                $caja->peso_min = $params_array['peso_min'];
                $caja->peso_standard = $params_array['peso_standard'];
                $caja->id_destino = $params_array['id_destino'];
                $caja->id_tipoCaja = $params_array['id_tipoCaja'];
                $caja->id_distrib = $params_array['id_distrib'];
                $caja->id_codAllweights = $params_array['id_codAllweights'];
                $caja->save();

                $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                $this->out['caja'] = $caja;
            }

        } else {
            $this->out['message'] = "No se han recibido parametros";
        }

        return response()->json($this->out, $this->out['code']);

    }

    public function show($id)
    {
        $caja = EMP_CAJA::find($id)->load(['destino', 'distribuidor', 'tipo_caja']);

        if (is_object($caja) && !empty($caja)) {
            $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
            $this->out['caja'] = $caja;
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function update(Request $request, $id)
    {
        $caja = EMP_CAJA::find($id);

        if (is_object($caja) && !empty($caja)) {
            $json = $request->input('json', null);
            $params = json_decode($json);
            $params_array = json_decode($json, true);

            if (!empty($params_array) && count($params_array) > 0) {
                $validacion = Validator::make($params_array,
                    [
                        'descripcion' =>
                            "required|unique:EMP_CAJAS,descripcion,NULL,NULL,id_destino,$params->id_destino,id_tipoCaja,$params->id_tipoCaja,id_distrib,$params->id_distrib",
                        'peso_max' => 'required|between:0,99.99',
                        'peso_min' => 'required|between:0,99.99',
                        'peso_standard' => 'required|between:0,99.99',
                        'id_destino' => ['required', 'exists:EMP_DESTINO,id'],
                        'id_tipoCaja' => ['required', 'exists:EMP_TIPO_CAJA,id'],
                        'id_distrib' => ['required', 'exists:EMP_DISTRIBUIDOR,id'],
                        'id_codAllweights' => 'between:0,9999999'
                    ],
                    [
                        'descripcion.unique' => 'La caja ya se encuentra registrada o no se han detectado cambios en el registro actual.'
                    ]);

                if ($validacion->fails()) {
                    $this->out['message'] = "Los datos enviados no son correctos";
                    $this->out['error'] = $validacion->errors();
                } else {
                    unset($params_array['id']);
                    unset($params_array['created_at']);

                    $caja->descripcion = $params_array['descripcion'];
                    $caja->peso_max = $params_array['peso_max'];
                    $caja->peso_min = $params_array['peso_min'];
                    $caja->peso_standard = $params_array['peso_standard'];
                    $caja->id_destino = $params_array['id_destino'];
                    $caja->id_tipoCaja = $params_array['id_tipoCaja'];
                    $caja->id_distrib = $params_array['id_distrib'];
                    $caja->id_codAllweights = $params_array['id_codAllweights'];
                    $caja->save();

                    $this->out = $this->respuesta_json('success', 200, 'Datos actualizados correctamente');
                    $this->out['caja'] = $caja;
                }

            } else {
                $this->out['message'] = "No se han recibido parametros.";
            }
        } else {
            $this->out['message'] = "No existen datos con el parametro enviado.";
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function destroy($id)
    {
        $cajas = EMP_CAJA::find($id);

        if (is_object($cajas) && !empty($cajas)) {
            $cajas->delete();
            $this->out = $this->respuesta_json('success', 200, 'Dato eliminado correctamente.');
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function respuesta_json(...$datos)
    {
        return array(
            'status' => $datos[0],
            'code' => $datos[1],
            'message' => $datos[2]
        );
    }

    private function getIdentity(Request $request)
    {
        $jwtauth = new JwtAuth();
        $token = $request->header('Authorization', null);
        $user = $jwtauth->checkToken($token, true);

        return $user;
    }
}
