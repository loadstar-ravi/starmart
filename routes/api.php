<?php

use App\Http\Controllers\Api\Auth\AccessTokenController;
use App\Http\Controllers\Api\Auth\RegisteredUserController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\OrderCancellationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProfilePasswordController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisteredUserController::class, 'store'])->name('api.register');
Route::post('/login', [AccessTokenController::class, 'store'])
    ->middleware('throttle:login')
    ->name('api.login');

Route::get('/products', [ProductController::class, 'index'])->name('api.products.index');
Route::get('/products/{product}', [ProductController::class, 'show'])
    ->whereNumber('product')
    ->name('api.products.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => new UserResource($request->user()))->name('api.user');
    Route::post('/logout', [AccessTokenController::class, 'destroy'])->name('api.logout');

    Route::middleware('customer')->group(function () {
        Route::get('/cart', [CartController::class, 'show'])->name('api.cart.show');
        Route::post('/cart', [CartItemController::class, 'store'])->name('api.cart.items.store');
        Route::put('/cart/{item}', [CartItemController::class, 'update'])
            ->whereNumber('item')
            ->name('api.cart.items.update');
        Route::delete('/cart/{item}', [CartItemController::class, 'destroy'])
            ->whereNumber('item')
            ->name('api.cart.items.destroy');

        Route::get('/orders', [OrderController::class, 'index'])->name('api.orders.index');
        Route::post('/orders', [OrderController::class, 'store'])->name('api.orders.store');
        Route::get('/orders/{order}', [OrderController::class, 'show'])
            ->whereNumber('order')
            ->name('api.orders.show');
        Route::post('/orders/{order}/cancel', [OrderCancellationController::class, 'store'])
            ->whereNumber('order')
            ->name('api.orders.cancel');

        Route::post('/payment/process', [PaymentController::class, 'store'])->name('api.payment.process');

        Route::put('/user', [ProfileController::class, 'update'])->name('api.user.update');
        Route::put('/user/password', [ProfilePasswordController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('api.user.password.update');
    });
});
