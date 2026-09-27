<?php

use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\ContactoController;
use App\Http\Controllers\Api\CuentaEfectivoController;
use App\Http\Controllers\Api\CuentaPendienteController;
use App\Http\Controllers\Api\MovimientoController;
use App\Http\Controllers\Api\Movimientos\CompraController;
use App\Http\Controllers\Api\Movimientos\VentaController;
use App\Http\Controllers\Api\Movimientos\ProduccionController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\StockProductoController;
use App\Http\Controllers\Api\SacoAlimentoController;
use App\Http\Controllers\Api\UnidadMedidaController;
use App\Http\Controllers\Api\LoteController;
use App\Http\Controllers\Api\HuevoStatsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/activity-logs', [ActivityLogController::class, 'index']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('contactos', ContactoController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('cuentas-efectivo', CuentaEfectivoController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/cuentas-efectivo/{cuentas_efectivo}/saldo', [CuentaEfectivoController::class, 'saldo']);
    Route::get('/cuentas-efectivo/{cuentas_efectivo}/movimientos', [CuentaEfectivoController::class, 'movimientos']);
    Route::apiResource('categorias', CategoriaController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('unidades-medida', UnidadMedidaController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('productos', ProductoController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/movimientos', [MovimientoController::class, 'index']);
    Route::post('/movimientos/aporte-capital', [MovimientoController::class, 'aportarCapital']);
    Route::post('/movimientos/retiro-capital', [MovimientoController::class, 'retirarCapital']);
    Route::post('/movimientos/transferencia', [MovimientoController::class, 'transferir']);
    Route::post('/movimientos/cobro', [MovimientoController::class, 'cobrar']);
    Route::post('/movimientos/pago', [MovimientoController::class, 'pagar']);
    Route::post('/movimientos/gasto-operativo', [MovimientoController::class, 'gastoOperativo']);

    Route::post('/compras', [CompraController::class, 'store']);
    Route::get('/compras/{id}', [CompraController::class, 'show']);
    Route::delete('/compras/{id}', [CompraController::class, 'destroy']);
    Route::post('/compras/{id}/anular', [CompraController::class, 'anular']);

    Route::post('/ventas', [VentaController::class, 'store']);
    Route::get('/ventas/{id}', [VentaController::class, 'show']);
    Route::delete('/ventas/{id}', [VentaController::class, 'destroy']);
    Route::post('/ventas/{id}/anular', [VentaController::class, 'anular']);

    Route::post('/producciones', [ProduccionController::class, 'store']);
    Route::get('/producciones/{id}', [ProduccionController::class, 'show']);
    Route::delete('/producciones/{id}', [ProduccionController::class, 'destroy']);
    Route::post('/producciones/{id}/anular', [ProduccionController::class, 'anular']);
    Route::get('/huevos/resumen', [HuevoStatsController::class, 'resumen']);

    Route::get('/cuentas-pendientes', [CuentaPendienteController::class, 'index']);
    Route::post('/cuentas-pendientes', [CuentaPendienteController::class, 'store']);
    Route::put('/cuentas-pendientes/{cuenta_pendiente}', [CuentaPendienteController::class, 'update']);
    Route::delete('/cuentas-pendientes/{cuenta_pendiente}', [CuentaPendienteController::class, 'destroy']);
    Route::get('/cuentas-pendientes/{cuenta_pendiente}/serie', [CuentaPendienteController::class, 'serie']);

    Route::get('/stock-productos', [StockProductoController::class, 'index']);
    Route::get('/stock-productos/producto/{producto}', [StockProductoController::class, 'stockActual']);
    Route::post('/stock-productos/consumo', [StockProductoController::class, 'registrarConsumo']);

    Route::get('/sacos-alimento', [SacoAlimentoController::class, 'index']);
    Route::get('/sacos-alimento/producto/{producto}/resumen', [SacoAlimentoController::class, 'resumen']);
    Route::post('/sacos-alimento/{saco_alimento}/iniciar-uso', [SacoAlimentoController::class, 'iniciarUso']);
    Route::post('/sacos-alimento/{saco_alimento}/terminar', [SacoAlimentoController::class, 'terminar']);

    Route::get('/lotes', [LoteController::class, 'index']);
    Route::post('/lotes/{lote}/corregir-sexo', [LoteController::class, 'corregirSexo']);
    Route::post('/lotes/{lote}/marcar-produccion', [LoteController::class, 'marcarProduccion']);

    Route::middleware('is_admin')->group(function () {
        Route::apiResource('admin/users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});