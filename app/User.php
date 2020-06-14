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
        'password', 'remember_token',
    ];

    public function empleado()
    {
        return $this->hasOne('App\Models\Hacienda\Empleado', 'id', 'idempleado');
    }

    public function hacienda()
    {
        return $this->hasOne('App\Models\Hacienda\Hacienda', 'id', 'idhacienda');
    }

    public function getDateFormat()
    {
        return config('constants.format_date');
    }

    public function perfil()
    {
        return $this->hasMany('App\Perfil', 'iduser', 'id');
    }

    public function recursos()
    {
        $this->hasMany('App\Perfil', 'iduser', 'id');
    }

    public $timestamps = false;
}
