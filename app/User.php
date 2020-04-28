<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'SIS_USUARIOS';

    protected $fillable = [
        'nombre', 'apellido', 'correo', 'contraseña', 'avatar'
    ];

    protected $hidden = [
        'id', 'password', 'remember_token',
    ];

    public function getDateFormat() {
        return 'd-m-Y H:i:s.v';
    }
}
