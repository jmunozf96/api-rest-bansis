<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Helpers\JwtAuth;
use App\Models\Hacienda\Empleado;
use App\Perfil;
use App\Recurso;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use function foo\func;

use Illuminate\Support\Facades\Mail;
use App\Mail\ConfirmacionAcceso;

class UserController extends Controller
{
    protected $respuesta;
    protected $servicios;

    public function __construct()
    {
        $this->servicios = new Helper();
        $this->respuesta = $this->response_array('error', 400, 'Describa mensaje');
    }

    public function create(Request $request)
    {
        $json = $request->input('json');
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if (is_object($params) && count($params_array) > 0) {

            $validacion = Validator::make($params_array, [
                'id' => 'required',
                'nombre' => 'required|regex:/^[\pL\s\-]+$/u',
                'apellido' => 'required|regex:/^[\pL\s\-]+$/u',
                'correo' => 'required|email',
                'password' => 'required',
                'descripcion' => 'required'
            ]);

            //|unique:SIS_Usuarios

            if ($validacion->fails()) {
                $this->respuesta['message'] = 'Los datos enviados no son correctos';
                $this->respuesta['error'] = $validacion->errors();
            } else {
                $usuario = User::where('idempleado', $params->id)->first();
                $password_hash = Hash::make($params->password, [
                    'memory' => 1024,
                    'time' => 2,
                    'threads' => 2,
                ]);
                if (!$usuario) {
                    //$password_hash = hash('sha256', $params->contraseña);
                    $usuario = new User();
                    $usuario->idempleado = $params->id;
                    $usuario->correo = $params->correo;
                    $usuario->nick = $this->generarNick($params->nombre, $params->apellido);
                    $usuario->contraseña = $password_hash;
                    $usuario->descripcion = strtoupper($params->descripcion);
                    $usuario->idhacienda = $params->idhacienda;
                    $usuario->estado = true;
                    $usuario->created_at = Carbon::now()->format(config('constants.format_date'));
                    $this->respuesta['message'] = 'Usuario registrado correctamente';
                } else {
                    $usuario->correo = $params->correo;
                    $usuario->contraseña = $password_hash;
                    $usuario->descripcion = strtoupper($params->descripcion);
                    $this->respuesta['message'] = 'Usuario actualizado correctamente';
                }
                $this->respuesta['code'] = 200;
                $this->respuesta['status'] = 'success';
                $usuario->updated_at = Carbon::now()->format(config('constants.format_date'));
                $usuario->save();
                $this->respuesta['nick'] = $usuario->nick;
                $this->respuesta['user'] = $usuario;
            }
        } else {
            $this->respuesta['message'] = 'No se han recibido datos';
        }

        return response()->json($this->respuesta, 200);
    }

    public function asignRecursos(Request $request)
    {
        try {
            $json = $request->input('json');
            $params = json_decode($json);
            $params_array = json_decode($json, true);

            if (is_object($params) && !empty($params)) {
                $validacion = Validator::make($params_array, [
                    'usuario' => 'required',
                    'roles' => 'required'
                ]);

                if (!$validacion->fails()) {
                    DB::beginTransaction();
                    $usuario = User::where(['idempleado' => $params_array['usuario']['id']])->first();
                    Perfil::where(['iduser' => $usuario->id])->delete();
                    foreach ($params_array['roles'] as $rol):
                        $existe_rol = Perfil::where([
                            'iduser' => $usuario->id,
                            'idrecurso' => $rol,
                        ])->first();

                        if (!is_object($existe_rol)) {
                            $perfil = new Perfil();
                            $perfil->iduser = $usuario->id;
                            $perfil->idrecurso = $rol;
                            $perfil->created_at = Carbon::now()->format(config('constants.format_date'));
                            $perfil->updated_at = Carbon::now()->format(config('constants.format_date'));
                            $perfil->save();
                        }
                    endforeach;
                    DB::commit();
                    $this->respuesta = $this->response_array('success', 200, 'Modulos asignados correctamente');
                    return response()->json($this->respuesta, 200);
                }
                throw new \Exception('No se han recibido roles de usuario');
            }
            throw new \Exception('No se han recibido parametros.');
        } catch (\Exception $ex) {
            DB::rollBack();
            $this->respuesta['message'] = $ex->getMessage();
            return response()->json($this->respuesta, 500);
        }
    }

    public function login(Request $request)
    {
        $jwtAuth = new JwtAuth();

        $json = $request->input('json');
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if (is_object($params) && count($params_array) > 0) {
            $validacion = Validator::make($params_array, [
                'user' => 'required',
                'password' => 'required'
            ]);

            if ($validacion->fails()) {
                $this->respuesta['message'] = 'Los parametros enviados no son correctos';
                $this->respuesta['error'] = $validacion->errors();
            } else {
                $signup = $jwtAuth->signup($params->user, $params->password);
                $credentials = $jwtAuth->signup($params->user, $params->password, true);

                if (!empty($params->getToken)) {
                    $signup = $credentials;
                } else {
                    if (isset($credentials['credential']))
                        Mail::to($credentials['credential']->correo)
                            ->send(new ConfirmacionAcceso($credentials['credential']));
                }

                return response()->json($signup, 200);
            }

        } else {
            $this->respuesta['message'] = "No se han recibido datos";
        }

        return response()->json($this->respuesta, 200);
    }

    protected function generarNick($nombres, $apellidos)
    {
        $nick = '';

        $array_nombres = explode(' ', $nombres);
        $array_apellidos = explode(' ', $apellidos);

        //Primera letra del nombre, apellido, y primera letra del segundo nombre
        $nick = strtolower(substr($array_nombres[0], 0, 1)) .
            strtolower($this->servicios->eliminar_acentos($array_apellidos[0])) .
            (isset($array_apellidos[1]) ?
                strtolower(substr($this->servicios->eliminar_acentos($array_apellidos[1]), 0, 1)) :
                strtolower(substr($this->servicios->eliminar_acentos($array_apellidos[0]), 0, 1)));

        $existe_nick = User::where('nick', trim($nick))->first();

        if (is_object($existe_nick)) {
            $numero = User::where('nick', trim($nick))->get();
            $numero = count($numero);
            $nick = $nick . strval($numero);
        }

        return $nick;
    }

    public function verifyToken(Request $request)
    {
        try {
            $token = $request->header('Authorization');

            if (!empty($token)) {
                $jwtauth = new JwtAuth();
                $checkTocken = $jwtauth->checkToken($token);

                return response()->json([
                    'code' => 200,
                    'logueado' => $checkTocken,
                    'token' => $jwtauth->checkToken($token, true)
                ], 200);
            }

            throw new \Exception('No se encontraron parametros');
        } catch (\Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function verifyModule(Request $request)
    {
        $salida = [];
        try {
            $salida['status'] = false;

            $json = $request->input('json');
            $params = json_decode($json);

            if (is_object($params) && isset($params->modulo) && isset($params->rutaPadre)) {
                $perfil = Perfil::where([
                    'iduser' => $params->idempleado,
                    'idrecurso' => $params->modulo
                ])
                    ->whereHas('recurso', function ($query) use ($params) {
                        $query->where([
                            'ruta' => $params->rutaPadre
                        ]);
                    })
                    ->first();

                if ($perfil) {
                    $salida['status'] = true;
                    return response()->json($salida, 200);
                } else {
                    $salida['recursos'] = $this->servicios->getRecursosUser($params->idempleado);
                    throw new \Exception('No puede acceder a este modulo.');
                }
            }
            throw new \Exception('No es un modulo de la base de datos');
        } catch (\Exception $ex) {
            $salida['message'] = $ex->getMessage();
            return response()->json($salida, 200);
        }
    }

    protected function response_array(...$data)
    {
        return [
            'status' => $data[0],
            'code' => $data[1],
            'message' => $data[2]
        ];
    }
}
