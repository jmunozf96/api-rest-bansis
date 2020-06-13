<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable;
    use HasRoles;

    protected $table = 'SIS_USUARIOS';

    protected $fillable = [
        'nombre', 'apellido', 'correo', 'contraseña', 'avatar'
    ];

    protected $hidden = [
        'id', 'password', 'remember_token',
    ];

    public function getDateFormat()
    {
        return config('constants.format_date');
    }

    public function recursos()
    {
        $this->hasMany('App\Perfil', 'iduser', 'id');
    }
}
