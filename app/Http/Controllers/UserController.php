<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\User;
use Illuminate\Http\Request;
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
                $password_hash = hash('sha256', $params->contraseña);

                $usuario = new User();
                $usuario->nombre = $params->nombre;
                $usuario->apellido = $params->apellido;
                $usuario->correo = $params->correo;
                $usuario->nick = $this->generarNick($params->nombre, $params->apellido);
                $usuario->contraseña = $password_hash;
                $usuario->descripcion = $params->descripcion;
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
                $password_hash = hash('sha256', trim($params->password));

                $usuario = User::where([
                    'correo' => trim(str_replace(" ", "", $params->user)),
                ])->orWhere([
                    'nick' => trim(str_replace(" ", "", $params->user)),
                ])->where([
                    'contraseña' => $password_hash,
                    'estado' => true
                ])->first();

                if (is_object($usuario)) {
                    $this->respuesta = $this->response_array('success', 200, 'Usuario logueado con éxito!');
                    $this->respuesta['usuario'] = $usuario;
                } else {
                    $this->respuesta['message'] = 'El usuario no se encuentra registrado.';
                }
            }
        } else {
            $this->respuesta['message'] = "No se han recibido datos";
        }

        return response()->json($this->respuesta, $this->respuesta['code']);
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

    protected function response_array(...$data)
    {
        return [
            'status' => $data[0],
            'code' => $data[1],
            'message' => $data[2]
        ];
    }
}
