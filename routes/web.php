<?php

use \Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

//Rutas para acceso y configuracion de usuarios
Route::post('/api/bansis/user/create', 'UserController@create')->middleware('api.auth');
Route::post('/api/bansis/login', 'UserController@login');

//Ruta resource para destinos de empacadora
Route::resource('/api/emp_destino', 'Empacadora\EmpDestinoController')->except([
    'create', 'edit'
]);

//Ruta resource para tipos de caja de empacadora
Route::resource('/api/emp_tipo_caja', 'Empacadora\EmpTipoCajaController')->except([
    'create', 'edit'
]);

//Ruta resource para distribuidores
Route::resource('/api/emp_distribuidor', 'Empacadora\EmpDistribuidorController')->except([
    'create', 'edit'
]);

Route::resource('/api/emp_caja', 'Empacadora\EmpCajaController')->except([
    'create', 'edit'
]);
