<?php

use \Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

//Rutas para acceso y configuracion de usuarios
Route::post('api/bansis/user/create', 'UserController@create')->middleware('api.auth');
Route::post('api/bansis/login', 'UserController@login');
