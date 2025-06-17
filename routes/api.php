<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\API\ShippingController;
use App\Http\Controllers\API\PaymentsController;
use App\Http\Controllers\API\MercadoPagoWebhookController;
use App\Http\Controllers\API\PedidoController;
use App\Http\Controllers\API\HighlightSectionController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\AdminResumenController;
use App\Http\Controllers\API\AdminFinanzasController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    // Rutas de productos
    // Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    // Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
});
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::middleware('auth:api')->group(function () {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::post('/cart/remove', [CartController::class, 'remove']);
    Route::post('/cart/clear', [CartController::class, 'clear']);
});

Route::middleware('auth:api')->post('/orders', [OrderController::class, 'store']);

Route::middleware('auth:api')->get('/my-orders', [OrderController::class, 'myOrders']);

Route::middleware('auth:api')->post('/orders/repeat/{id}', [OrderController::class, 'repeat']);



Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categorias/{id}/productos', [CategoryController::class, 'productosPorCategoria']);

Route::get('/products/category/{id}', [ProductController::class, 'byCategory']);

Route::middleware('auth:api')->post('/address', [AddressController::class, 'store']);

Route::get('/estados', [EstadoController::class, 'index']);
Route::get('/estados/{id}/municipios', [EstadoController::class, 'municipios']);

Route::post('/direccion-extra', [AddressController::class, 'guardarInfoExtra']);

Route::get('/direccion-completa/{id}', [AddressController::class, 'direccionCompleta']);

Route::get('/address', [AddressController::class, 'index']);

Route::put('/address/{id}', [AddressController::class, 'update']);

Route::middleware(['auth:api'])->post('/shipping/quote', [ShippingController::class, 'quote']);

Route::post('/shipping/quote', [ShippingController::class, 'quote']);

Route::post('/envio/costo', [ShippingController::class, 'calcularCosto']);

// Route::post('/pago/preferencia', [\App\Http\Controllers\API\PaymentsController::class, 'createPreference']);

Route::post('/webhook/mercadopago', [\App\Http\Controllers\API\MercadoPagoWebhookController::class, 'handle']);

Route::middleware('auth:api')->post('/pago/preferencia', [PaymentsController::class, 'createPreference']);

Route::middleware('auth:api')->get('/ultimo-pedido', [PedidoController::class, 'ultimo']);

Route::middleware('auth:api')->get('/mis-pedidos', [PedidoController::class, 'misPedidos']);

Route::middleware('auth:api')->post('/repeat-pedido/{id}', [PedidoController::class, 'repeatPedido']);

Route::get('/highlight-sections', [HighlightSectionController::class, 'index']);
Route::post('/highlight-sync', [HighlightSectionController::class, 'sync']);

Route::get('/pedidos/excedidos', [PedidoController::class, 'excedidos']);


// routes/api.php

Route::middleware('auth:api')->prefix('admin')->group(function () {
    // Tus rutas existentes para pedidos
    Route::get('/pedidos', [AdminController::class, 'index']); // O PedidoController si es el que usas
    Route::get('/pedidos/{id}/items', [AdminController::class, 'items']); // O PedidoController

    // === NUEVA RUTA PARA LOS DETALLES COMPLETOS DEL PEDIDO ===
    Route::get('/pedidos/{id}/details', [AdminController::class, 'showPedidoDetails']);
});

Route::put('products/{id}/status', [ProductController::class, 'updateStatus']); // <--- NUEVA RUTA

Route::prefix('admin')->group(function () {
    Route::put('pedidos/{id}/shipment-status', [PedidoController::class, 'updateShipmentStatus']);
});

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::get('resumen/pedidos-pendientes', [AdminResumenController::class, 'pedidosPendientes']);
    Route::get('resumen/productos-bajo-stock', [AdminResumenController::class, 'productosBajoStock']);
    Route::get('resumen/pedidos-retrasados', [AdminResumenController::class, 'pedidosRetrasados']);
    Route::get('resumen/productos-categoria', [AdminResumenController::class, 'productosPorCategorianuevo']);
    Route::get('finanzas/resumen', [AdminFinanzasController::class, 'resumenFinanzas']);
});


