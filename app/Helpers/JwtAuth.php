<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use http\Client\Curl\User;

class JwtAuth
{
    public $key;

    public function __construct()
    {
        $this->key = 'jdmfadmin12311022020';
    }

    public function signup($user, $password, $getToken = null)
    {
        $user = \App\User::where([
            'correo' => trim(str_replace(" ", "", $user)),
        ])->orWhere([
            'nick' => trim(str_replace(" ", "", $user)),
        ])->where([
            'contraseña' => $password,
            'estado' => true
        ])->first();

        $signup = false;

        if (is_object($user)) {
            $signup = true;
        }

        if ($signup) {
            $token = array(
                'sub' => $user->id,
                'nick' => $user->nick,
                'correo' => $user->correo,
                'nombres' => $user->nombre,
                'apellidos' => $user->apellido,
                'iat' => time(),
                'exp' => time() + (7 * 24 * 60 * 60)
            );

            $jwt = JWT::encode($token, $this->key, 'HS256');
            $decode = JWT::decode($jwt, $this->key, ['HS256']);

            if (is_null($getToken)) {
                $data = $jwt;
            } else {
                $data = $decode;
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
