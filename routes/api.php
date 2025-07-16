<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\CategoryFeaturedProductController;
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
use App\Http\Controllers\API\UsuarioController;
use App\Http\Controllers\API\SoporteController;
use App\Http\Controllers\API\SubcategoryController;
use App\Http\Controllers\API\SepomexController;
use App\Http\Controllers\UserFavoriteController;
use App\Http\Controllers\ReviewController;

use App\Http\Middleware\ActualizarUltimaActividad;
use App\Http\Middleware\CheckRevokedToken;
use App\Http\Middleware\EnsureEmailIsVerified;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/products', [ProductController::class, 'store']); 
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::delete('/gallery-images/{id}', [ProductImageController::class, 'destroy']);
});


Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->group(function () {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::post('/cart/remove', [CartController::class, 'remove']);
    Route::post('/cart/clear', [CartController::class, 'clear']);
});


Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->post('/orders', [OrderController::class, 'store']);
Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->get('/my-orders', [OrderController::class, 'myOrders']);
Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->post('/orders/repeat/{id}', [OrderController::class, 'repeat']);


Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categorias/{id}/productos', [CategoryController::class, 'productosPorCategoria']);
Route::get('/products/category/{id}', [ProductController::class, 'byCategory']);

Route::middleware('auth:api')->post('/address', [AddressController::class, 'store']);
Route::get('/estados', [EstadoController::class, 'index']);
Route::get('/estados/{id}/municipios', [EstadoController::class, 'municipios']);

Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->post('/direccion-extra', [AddressController::class, 'guardarInfoExtra']);
Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->get('/direccion-completa/{id}', [AddressController::class, 'direccionCompleta']);
Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->get('/address', [AddressController::class, 'index']);
Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->put('/address/{id}', [AddressController::class, 'update']);
Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->delete('direcciones/{id}', [AddressController::class, 'destroy']);


Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->post('/shipping/quote', [ShippingController::class, 'quote']);

Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])->post('/envio/costo', [ShippingController::class, 'calcularCosto']);


Route::post('/webhook/mercadopago', [MercadoPagoWebhookController::class, 'handle']);

Route::middleware(['auth:api', CheckRevokedToken::class, EnsureEmailIsVerified::class])
    ->post('/pago/preferencia', [PaymentsController::class, 'createPreference']);

Route::middleware('auth:api')->get('/ultimo-pedido', [PedidoController::class, 'ultimo']);
Route::middleware('auth:api')->get('/mis-pedidos', [PedidoController::class, 'misPedidos']);
Route::middleware('auth:api')->post('/repeat-pedido/{id}', [PedidoController::class, 'repeatPedido']);

Route::get('/highlight-sections', [HighlightSectionController::class, 'index']);
Route::post('/highlight-sync', [HighlightSectionController::class, 'sync']);
Route::get('/pedidos/excedidos', [PedidoController::class, 'excedidos']);

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::get('/pedidos', [AdminController::class, 'index']);
    Route::get('/pedidos/{id}/items', [AdminController::class, 'items']);
    Route::get('/pedidos/{id}/details', [AdminController::class, 'showPedidoDetails']);
});

Route::put('products/{id}/status', [ProductController::class, 'updateStatus']);

Route::prefix('admin')->group(function () {
    Route::put('pedidos/{id}/shipment-status', [PedidoController::class, 'updateShipmentStatus']);
});

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::get('resumen/pedidos-pendientes', [AdminResumenController::class, 'pedidosPendientes']);
    Route::get('resumen/productos-bajo-stock', [AdminResumenController::class, 'productosBajoStock']);
    Route::get('resumen/pedidos-retrasados', [AdminResumenController::class, 'pedidosRetrasados']);
    Route::get('resumen/productos-categoria', [AdminResumenController::class, 'productosPorCategorianuevo']);
    Route::get('finanzas/resumen', [AdminFinanzasController::class, 'resumenFinanzas']);
    Route::get('/categorias-con-subcategorias-productos', [CategoryController::class, 'indexConSubcategoriasYProductos']);
});

// 🔐 Rutas de seguridad con actividad registrada
Route::middleware(['auth:api', ActualizarUltimaActividad::class, CheckRevokedToken::class, EnsureEmailIsVerified::class])->group(function () {
    Route::post('/cambiar-contrasena', [UsuarioController::class, 'cambiarContrasena']);
    Route::get('/actividad-reciente', [UsuarioController::class, 'actividadReciente']);
    Route::get('/sesiones-activas', [UsuarioController::class, 'sesionesActivas']);
    Route::delete('/cerrar-sesion/{id}', [UsuarioController::class, 'cerrarSesion']);
});

Route::middleware('auth:api')->post('/logout', [AuthController::class, 'logout']);

Route::get('categories/{id}', [CategoryController::class, 'show']);
Route::get('subcategories/category/{id}', [SubcategoryController::class, 'getByCategory']);

Route::prefix('admin')->middleware('auth:api')->group(function () {
    Route::get('categories/{category}/featured-products', [CategoryFeaturedProductController::class, 'index']);
    Route::post('categories/featured-products', [CategoryFeaturedProductController::class, 'store']);
    Route::delete('categories/featured-products/{id}', [CategoryFeaturedProductController::class, 'destroy']);
    
});

Route::get('categories/{category}/featured-only-products', [CategoryFeaturedProductController::class, 'featuredProducts']);

Route::post('/refresh-token', [AuthController::class, 'refresh']);


// Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
//     ->name('verification.verify');


Route::middleware('auth:api')->group(function () {
    Route::get('/favorites', [UserFavoriteController::class, 'index']);
    Route::post('/favorites', [UserFavoriteController::class, 'store']);
    Route::delete('/favorites/{productId}', [UserFavoriteController::class, 'destroy']);
    
    // Nueva ruta para verificar si un producto es favorito
    Route::get('/favorites/check/{productId}', [UserFavoriteController::class, 'checkFavorite']);
});


Route::get('/products/{id}/reviews', [ReviewController::class, 'index']);
Route::middleware('auth:api')->post('/products/{id}/reviews', [ReviewController::class, 'store']);

// routes/api.php
Route::post('/soporte', [SoporteController::class, 'enviarConsulta']);

Route::get('/sepomex/estado/{id}', [SepomexController::class, 'porEstado']);
