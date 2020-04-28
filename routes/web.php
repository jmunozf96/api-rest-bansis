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
Route::apiResource('/api/bansis-app/index.php/haciendas', 'Hacienda\HaciendaController');
Route::get('/api/bansis-app/index.php/haciendas-select', 'Hacienda\HaciendaController@customSelect');
//Ruta resource para labores
Route::apiResource('/api/bansis-app/index.php/labores', 'Hacienda\LaborController');
Route::get('/api/bansis-app/index.php/labores-select', 'Hacienda\LaborController@customSelect');
//Ruta resource para empleados
Route::apiResource('/api/bansis-app/index.php/empleados', 'Hacienda\EmpleadoController');


//Api Bodega
Route::apiResource('/api/bansis-app/index.php/bodegas', 'Bodega\BodegaController');
Route::get('/api/bansis-app/custom.php/bodegas/option', 'Bodega\BodegaController@getOptions');
Route::apiResource('/api/bansis-app/index.php/bodega-grupos', 'Bodega\GrupoController');
Route::get('/api/bansis-app/custom.php/bodegas/grupos/option', 'Bodega\GrupoController@getOptions');

//Api xass
Route::get('/api/bansis-app/XassInventario.php/primo/productos', 'XassInventario\Primo\ProductoController@getProductos');
Route::get('/api/bansis-app/XassInventario.php/primo/grupo/padre', 'XassInventario\Primo\GrupoController@getGruposPadre');
Route::get('/api/bansis-app/XassInventario.php/primo/grupo/hijo/{idpadre}', 'XassInventario\Primo\GrupoController@getGruposHijos');
Route::get('/api/bansis-app/XassInventario.php/primo/bodegas', 'XassInventario\Primo\BodegaController@getBodegas');

//Api Material
Route::apiResource('/api/bansis-app/index.php/materiales', 'Bodega\MaterialController');
Route::put('api/bansis-app/custom.php/materiales/updateStock/{codigo}', 'Bodega\MaterialController@updateStockMaterial');
