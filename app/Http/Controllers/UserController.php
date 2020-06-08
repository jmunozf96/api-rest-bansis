<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Helpers\JwtAuth;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
                'nombre' => 'required|regex:/^[\pL\s\-]+$/u',
                'apellido' => 'required|regex:/^[\pL\s\-]+$/u',
                'correo' => 'required|email|unique:SIS_Usuarios',
                'contraseña' => 'required',
                'descripcion' => 'required'
            ]);

            if ($validacion->fails()) {
                $this->respuesta['message'] = 'Los datos enviados no son correctos';
                $this->respuesta['error'] = $validacion->errors();
            } else {
                //$password_hash = hash('sha256', $params->contraseña);
                $password_hash = Hash::make($params->contraseña, [
                    'memory' => 1024,
                    'time' => 2,
                    'threads' => 2,
                ]);

                $usuario = new User();
                $usuario->nombre = $params->nombre;
                $usuario->apellido = $params->apellido;
                $usuario->correo = $params->correo;
                $usuario->nick = $this->generarNick($params->nombre, $params->apellido);
                $usuario->contraseña = $password_hash;
                $usuario->descripcion = $params->descripcion;
                //$usuario->idhacienda = $params->idhacienda;
                $usuario->estado = true;

                $usuario->save();

                $this->respuesta['message'] = 'Usuario registrado correctamente';
                $this->respuesta['nick'] = $usuario->nick;
                $this->respuesta['user'] = $usuario;
            }
        } else {
            $this->respuesta['message'] = 'No se han recibido datos';
        }

        return response()->json($this->respuesta, 200);
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

                if (!empty($params->getToken)) {
                    $signup = $jwtAuth->signup($params->user, $params->password, true);
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
        $token = $request->header('Authorization');
        $jwtauth = new JwtAuth();
        $checkTocken = $jwtauth->checkToken($token);
        return response()->json([
            'logueado' => $checkTocken
        ], 200);
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
