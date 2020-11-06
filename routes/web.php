<?php

use \Illuminate\Support\Facades\Route;

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");


Route::get('/', function () {
    return view('welcome');
});

//Rutas para acceso y configuracion de usuarios
Route::post('/api/bansis/user/create', 'UserController@create');
Route::post('/api/bansis/user/asignModule', 'UserController@asignRecursos');
Route::post('/api/bansis/login', 'UserController@login');
Route::post('/api/bansis/verifyToken', 'UserController@verifyToken');
Route::post('/api/bansis/verifyModule', 'UserController@verifyModule');

//Ruta para los recursos del sistema
Route::get('api/bansis/recursos', 'RecursosController@index');

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
Route::get('/api/bansis-app/custom.php/haciendas/option', 'Hacienda\HaciendaController@getOptions');

//Ruta resource para labores
Route::apiResource('/api/bansis-app/index.php/labores', 'Hacienda\LaborController');
Route::get('/api/bansis-app/index.php/labores-select', 'Hacienda\LaborController@customSelect');

//Ruta resource para empleados
Route::apiResource('/api/bansis-app/index.php/empleados', 'Hacienda\EmpleadoController');
Route::get('/api/bansis-app/index.php/search/empleados', 'Hacienda\EmpleadoController@getEmpleados');
Route::get('/api/bansis-app/index.php/search/empleados/{hacienda}/{empleado}/inventario', 'Hacienda\EmpleadoController@getEmpleadosInventario');

//Api Bodega
Route::apiResource('/api/bansis-app/index.php/bodegas', 'Bodega\BodegaController');
Route::get('/api/bansis-app/index.php/bodegas-select', 'Bodega\BodegaController@customSelect');
Route::get('/api/bansis-app/custom.php/bodegas/option', 'Bodega\BodegaController@getOptions');
Route::apiResource('/api/bansis-app/index.php/bodega-grupos', 'Bodega\GrupoController');
Route::get('/api/bansis-app/index.php/bodegas-grupos-select', 'Bodega\GrupoController@customSelect');
Route::get('/api/bansis-app/custom.php/bodegas/grupos/option', 'Bodega\GrupoController@getOptions');


Route::prefix('api/bansis-app/index.php/bodega')->group(function () {
    Route::apiResource('egresos', 'Bodega\BodEgresosController');
    Route::prefix('search-egresos')->group(function () {
        Route::get('id/{idTransaccion}', 'Bodega\BodEgresosController@showById');
        Route::get('empleado/{empleado}', 'Bodega\BodEgresosController@showByEmpleado');
    });
    Route::prefix('transferencia')->group(function () {
        Route::get('search-saldos', 'Bodega\BodEgresosController@saldosEmpleado');
    });
});

Route::prefix('api/bansis-app/index.php/cosecha')->group(function () {
    Route::get('primo/balanza', 'Sisban\Cosecha\CosechaController@executeEventBalanzaPrimo');
    Route::get('sofca/balanza', 'Sisban\Cosecha\CosechaController@executeEventBalanzaSofca');
    Route::post('loading/data', 'Sisban\Cosecha\CosechaController@loadingData');
    Route::get('{hacienda}/cajas-dia', 'Sisban\Cosecha\CosechaController@getCajasDia');
    Route::prefix('informe')->group(function () {
        Route::get('manos-recusadas/{hacienda}/danos', 'Sisban\Cosecha\ManosRecusadasController@getDanos');
        Route::get('manos-recusadas/{hacienda}', 'Sisban\Cosecha\ManosRecusadasController@index');
    });
});


//Api xass
Route::get('/api/bansis-app/XassInventario.php/primo/productos', 'XassInventario\Primo\ProductoController@getProductos');
Route::get('/api/bansis-app/XassInventario.php/primo/grupo/padre', 'XassInventario\Primo\GrupoController@getGruposPadre');
Route::get('/api/bansis-app/XassInventario.php/primo/grupo/hijo/{idpadre}', 'XassInventario\Primo\GrupoController@getGruposHijos');
Route::get('/api/bansis-app/XassInventario.php/primo/bodegas', 'XassInventario\Primo\BodegaController@getBodegas');

Route::get('/api/bansis-app/XassInventario.php/sofca/productos', 'XassInventario\Sofca\ProductoController@getProductos');
Route::get('/api/bansis-app/XassInventario.php/sofca/grupo/padre', 'XassInventario\Sofca\GrupoController@getGruposPadre');
Route::get('/api/bansis-app/XassInventario.php/sofca/grupo/hijo/{idpadre}', 'XassInventario\Sofca\GrupoController@getGruposHijos');
Route::get('/api/bansis-app/XassInventario.php/sofca/bodegas', 'XassInventario\Sofca\BodegaController@getBodegas');

//Api Material
Route::apiResource('/api/bansis-app/index.php/materiales', 'Bodega\MaterialController');
Route::put('api/bansis-app/custom.php/materiales/updateStock', 'Bodega\MaterialController@updateStockMaterial');
Route::get('/api/bansis-app/index.php/search/materiales', 'Bodega\MaterialController@getMateriales');

//Api  Egresos Bodega
Route::apiResource('api/bansis-app/index.php/egreso-bodega', 'Bodega\EgresoBodegaController');
Route::get('api/bansis-app/index.php/search-egreso', 'Bodega\EgresoBodegaController@getTransaccion');
Route::get('api/bansis-app/index.php/show-transaction', 'Bodega\EgresoBodegaController@showTransferencia');
Route::delete('api/bansis-app/index.php/delete-transaction', 'Bodega\EgresoBodegaController@deleteCreditSaldo');
Route::post('api/bansis-app/index.php/egreso-bodega/saldos/transfer', 'Bodega\EgresoBodegaController@saldoAcreditadoDebitado');

//Api Lotes Hacienda
Route::apiResource('api/bansis-app/index.php/lote', 'Hacienda\LoteController');
Route::get('/api/bansis-app/index.php/lotes-select', 'Hacienda\LoteController@customSelect');

//Api Lotes seccion Hacienda
Route::apiResource('api/bansis-app/index.php/lote-seccion', 'Hacienda\LoteSeccionController');
Route::get('/api/bansis-app/index.php/lotes-seccion-select', 'Hacienda\LoteSeccionController@customSelect');

//Api Lotes seccion labor empleado Hacienda
Route::apiResource('api/bansis-app/index.php/lote-seccion-labor', 'Hacienda\LoteSeccionLaborEmpController');
Route::get('api/bansis-app/index.php/get-data/lote-seccion-labor', 'Hacienda\LoteSeccionLaborEmpController@getLaboresSeccionEmpleado');
Route::get('api/bansis-app/index.php/get-data/has-seccion', 'Hacienda\LoteSeccionLaborEmpController@getHasSeccionDisponibles');
Route::delete('api/bansis-app/index.php/lote-seccion-labor-detalle/{id}', 'Hacienda\LoteSeccionLaborEmpController@destroyDetalle');

//Api Calendario
Route::get('api/bansis-app/calendario.php/semanaEnfunde', 'Hacienda\CalendarioController@semanaEnfunde');
Route::get('api/bansis-app/calendario.php/semanasPeriodo', 'Hacienda\CalendarioController@semanasPeriodo');

//Api Enfunde
Route::apiResource('api/bansis-app/index.php/enfunde', 'Hacienda\EnfundeController');
Route::get('api/bansis-app/index.php/getEnfundeSeccion', 'Hacienda\EnfundeController@getEnfundeSeccion');
Route::get('api/bansis-app/index.php/getLotero', 'Hacienda\EnfundeController@getLoteros');
Route::get('api/bansis-app/index.php/getEnfunde/empleado', 'Hacienda\EnfundeController@getEnfundeDetalle');
Route::get('api/bansis-app/index.php/getEnfunde/semanal', 'Hacienda\EnfundeController@getEnfundeSemanal');
Route::get('api/bansis-app/index.php/getEnfunde/semanal/detalle/{id}', 'Hacienda\EnfundeController@getEnfundeSemanalDetail');
Route::post('api/bansis-app/index.php/endunde/cerrar/semana/{id}', 'Hacienda\EnfundeController@closeEnfundeSemanal');
Route::delete('api/bansis-app/index.php/deleteEnfunde/empleado', 'Hacienda\EnfundeController@deleteEnfunde');
//Informes
Route::get('api/bansis-app/index.php/informe/enfunde/semanal', 'Hacienda\EnfundeController@informeSemanalEnfunde');
Route::get('api/bansis-app/index.php/informe/enfunde/semanal-material', 'Hacienda\EnfundeController@informeSemanalEnfundeMaterial');
Route::get('api/bansis-app/index.php/informe/enfunde/semanal-empleados', 'Hacienda\EnfundeController@informeSemanalEnfundeEmpleados');
Route::get('api/bansis-app/index.php/informe/enfunde/semanal-empleados/detalle', 'Hacienda\EnfundeController@informeSmanalEnfundeEmpleadoMaterial');

//PDF
Route::get('api/bansis-app/index.php/informe/enfunde-pdf/semanal-empleados', 'Hacienda\EnfundeController@enfundeSemanal_PDF');

//Api HelperCosecha
Route::get('api/bansis-app/index.php/recepcion/{hacienda}/cintas-semana', 'Sisban\CosechaController@getCintasSemana');
Route::get('api/bansis-app/index.php/recepcion/{hacienda}/cintaRecobro', 'Sisban\CosechaController@getCintaRecobro');
Route::get('api/bansis-app/index.php/recepcion/{hacienda}/status', 'Sisban\CosechaController@statusCosecha');
Route::get('api/bansis-app/index.php/recepcion/{hacienda}/cosecha', 'Sisban\CosechaController@getCosecha');
Route::get('api/bansis-app/index.php/recepcion/{hacienda}/cosecha-lote', 'Sisban\CosechaController@getCosechaLote');
Route::get('api/bansis-app/index.php/recepcion/{hacienda}/cosecha-lotes', 'Sisban\CosechaController@getLotesCortadosDia');
Route::get('api/bansis-app/index.php/recepcion/{hacienda}/cajas-dia', 'Sisban\CosechaController@getCajasDia');
Route::get('api/bansis-app/index.php/recepcion/{hacienda}/lotesRecobro', 'Sisban\CosechaController@getLotesRecobro');

//Dashboard Enfunde
Route::get('api/bansis-app/index.php/dashboard/enfunde/enfunde-periodo', 'Hacienda\EnfundeController@dashboardEnfundePeriodo');
Route::get('api/bansis-app/index.php/dashboard/enfunde/enfunde-lote', 'Hacienda\EnfundeController@dashboardEnfundeLoteHacienda');
Route::get('api/bansis-app/index.php/dashboard/enfunde/enfunde-lotero', 'Hacienda\EnfundeController@dashboardEnfundeLoteLotero');
Route::get('api/bansis-app/index.php/dashboard/enfunde/enfunde-hacienda', 'Hacienda\EnfundeController@dashboardEnfundeHacienda');
Route::get('api/bansis-app/index.php/dashboard/enfunde/enfunde-historico', 'Hacienda\EnfundeController@dashboardEnfundeHistorico');
Route::get('api/bansis-app/index.php/dashboard/enfunde/enfunde-has', 'Hacienda\EnfundeController@dashboardEnfundeHectareas');
Route::get('api/bansis-app/index.php/dashboard/enfunde/enfunde-has-dataLote', 'Hacienda\EnfundeController@dashboardEnfundeSemanalLote');
Route::get('api/bansis-app/index.php/dashboard/enfunde/enfunde-lote-data', 'Hacienda\EnfundeController@getLoterosLoteEnfunde');
Route::get('api/bansis-app/index.php/dashboard/enfunde/enfunde-lotero-data', 'Hacienda\EnfundeController@getLotesLoteroEnfunde');

//Api Android
Route::apiResource('api/bansis-app/index.php/fitos/coordenadas', 'Sistema\CoordenadasFitoController');
Route::apiResource('api/bansis-app/index.php/cosecha/manos', 'Sistema\ManosRecusadasController');
Route::apiResource('api/bansis-app/index.php/hacienda/danos', 'Sistema\DanoController');
