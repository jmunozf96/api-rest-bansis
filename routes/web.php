<?php

use \Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

//Rutas para acceso y configuracion de usuarios
Route::post('/api/bansis/user/create', 'UserController@create');
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

//Ruta resource para cajas
Route::resource('/api/emp_caja', 'Empacadora\EmpCajaController')->except([
    'create', 'edit'
]);

//Ruta resource para codigos coorporativos
Route::resource('/api/emp_cod_coorp', 'Empacadora\EmpCodCoorpController')->except([
    'create', 'edit'
]);


//----------------------------------------------------------------------------------------------------------------------
//Ruta resource para bodegas
Route::resource('/api/bod_bodega', 'Bodega\BodBodegaController')->except([
    'create', 'edit'
]);

//Ruta resource para haciendas
Route::resource('/api/bansis-app/index.php/haciendas', 'Hacienda\HaciendaController')->except([
    'create', 'edit'
]);
Route::get('/api/bansis-app/index.php/haciendas-select', 'Hacienda\HaciendaController@customSelect');

//Ruta resource para labores
Route::resource('/api/bansis-app/index.php/labores', 'Hacienda\LaborController')->except([
    'create', 'edit'
]);
Route::get('/api/bansis-app/index.php/labores-select', 'Hacienda\LaborController@customSelect');

//Ruta resource para empleados
Route::resource('/api/bansis-app/index.php/empleados', 'Hacienda\EmpleadoController')->except([
    'create', 'edit'
]);

