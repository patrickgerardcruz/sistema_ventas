<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(session()->has('store_id') ? 'dashboard' : 'login'));
Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:6,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/plataforma/login', [AuthController::class, 'platformLogin'])->name('platform.login');
Route::post('/plataforma/login', [AuthController::class, 'authenticatePlatform'])->middleware('throttle:6,1');
Route::middleware('platform')->prefix('plataforma')->group(function () {
    Route::get('/', [PlatformController::class, 'index'])->name('platform.index');
    Route::post('/negocios', [PlatformController::class, 'store'])->name('platform.store');
    Route::put('/negocios/{id}', [PlatformController::class, 'update'])->name('platform.update');
});
Route::middleware('store')->group(function () {
    Route::get('/dashboard', [StoreController::class, 'dashboard'])->name('dashboard');
    Route::get('/catalogo/{module}', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalogo/{module}/{id}/editar', [CatalogController::class, 'edit'])->whereNumber('id')->name('catalog.edit');
    Route::post('/catalogo/{module}', [CatalogController::class, 'save'])->name('catalog.store');
    Route::put('/catalogo/{module}/{id}', [CatalogController::class, 'save'])->whereNumber('id')->name('catalog.update');
    Route::get('/inventario', [StoreController::class, 'inventory'])->name('inventory');
    Route::post('/inventario/ajuste', [StoreController::class, 'adjust'])->name('inventory.adjust');
    Route::get('/ventas', [StoreController::class, 'sales'])->name('sales');
    Route::get('/compras', [StoreController::class, 'purchases'])->name('purchases');
    Route::get('/operacion/{mode}', [StoreController::class, 'order'])->name('order');
    Route::post('/ventas', [StoreController::class, 'saveSale'])->name('sales.store');
    Route::post('/compras', [StoreController::class, 'savePurchase'])->name('purchases.store');
    Route::get('/ventas/{id}/ticket', [StoreController::class, 'ticket'])->whereNumber('id')->name('ticket');
    Route::get('/compras/{id}', [StoreController::class, 'purchaseDetail'])->whereNumber('id')->name('purchase.detail');
    Route::get('/caja', [StoreController::class, 'cash'])->name('cash');
    Route::post('/caja/{action}', [StoreController::class, 'cashAction'])->name('cash.action');
    Route::get('/devoluciones', [StoreController::class, 'returns'])->name('returns');
    Route::post('/devoluciones', [StoreController::class, 'refund'])->name('returns.store');
    Route::get('/auditoria', [StoreController::class, 'audit'])->name('audit');
    Route::get('/reportes', [StoreController::class, 'reports'])->name('reports');
    Route::get('/configuracion', [StoreController::class, 'settings'])->name('settings');
    Route::put('/configuracion', [StoreController::class, 'saveSettings'])->name('settings.save');
    Route::post('/respaldo', [StoreController::class, 'backup'])->name('backup');
});
