<?php

namespace App\Helpers;

use App\Helpers\Helper;
use App\Perfil;
use App\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Hash;

class JwtAuth
{
    public $key;
    protected $servicios;

    public function __construct()
    {
        $this->servicios = new Helper();
        $this->key = 'jdmfadmin12311022020';
    }

    public function signup($user, $password, $getToken = null)
    {
        $usuario = User::where([
            'correo' => trim(str_replace(" ", "", $user))
        ])->orWhere([
            'nick' => trim(str_replace(" ", "", $user))
        ])->where([
            'estado' => true
        ])->with(['empleado' => function ($query) {
            $query->select('id', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'idhacienda');
        }])->with(['hacienda' => function ($query) {
            $query->select('id', 'detalle as descripcion');
        }])->first();

        $signup = false;

        if (is_object($usuario)) {
            if (Hash::check($password, $usuario->contraseña)) {
                $signup = true;
            }
        }

        if ($signup) {
            $token = array(
                'sub' => $usuario->id,
                'nick' => $usuario->nick,
                'correo' => $usuario->correo,
                'nombres' => $usuario->empleado->nombre1 . ' ' . $usuario->empleado->nombre2,
                'apellidos' => $usuario->empleado->apellido1 . ' ' . $usuario->empleado->apellido2,
                'idhacienda' => $usuario->hacienda,
                'iat' => time(),
                'exp' => time() + (7 * 24 * 60 * 60)
            );

            $jwt = JWT::encode($token, $this->key, 'HS256');
            $decode = JWT::decode($jwt, $this->key, ['HS256']);

            $data = array(
                'status' => 'success',
                'code' => 200,
                'token' => false,
                'recursos' => $this->servicios->getRecursosUser($usuario->id)
            );

            if (is_null($getToken)) {
                $data['token'] = true;
                $data['credential'] = $jwt;
            } else {
                $data['credential'] = $decode;
            }
        } else {
            $data = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Login incorrecto'
            );
        }

        return $data;
    }

    public function checkToken($jwt, $getIdentity = false)
    {
        $auth = false;

        try {
            $jwt = str_replace('"', '', $jwt);
            $decode = JWT::decode($jwt, $this->key, ['HS256']);
        } catch (\UnexpectedValueException $ex) {
            $auth = false;
        } catch (\DomainException $ex) {
            $auth = false;
        }

        if (!empty($decode) && is_object($decode) && isset($decode->sub)) {
            $auth = true;
        } else {
            $auth = false;
        }

        if ($getIdentity) {
            return $decode;
        }

        return $auth;
    }
}
