<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController as AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CategoryStatusController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\OrderStatusController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\ProductStatusController;
use App\Http\Controllers\Admin\ProductStockController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderCancellationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderSuccessController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'customer'])->group(function () {
    Route::prefix('cart')->name('cart.')->group(function () {
        Route::get('/', [CartController::class, 'show'])->name('show');
        Route::post('/items', [CartItemController::class, 'store'])->name('items.store');
        Route::patch('/items/{item}', [CartItemController::class, 'update'])
            ->whereNumber('item')
            ->name('items.update');
        Route::delete('/items/{item}', [CartItemController::class, 'destroy'])
            ->whereNumber('item')
            ->name('items.destroy');
    });

    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order:order_number}/success', OrderSuccessController::class)->name('orders.success');
    Route::post('/orders/{order:order_number}/cancel', [OrderCancellationController::class, 'store'])
        ->name('orders.cancel');

    Route::get('/orders/{order:order_number}/payment', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AdminAuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:login')
            ->name('login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::resource('categories', CategoryController::class)->except('show');
        Route::patch('/categories/{category}/status', [CategoryStatusController::class, 'update'])
            ->name('categories.status.update');

        Route::resource('products', AdminProductController::class)->except('show');
        Route::patch('/products/{product}/status', [ProductStatusController::class, 'update'])
            ->name('products.status.update');
        Route::patch('/products/{product}/stock', [ProductStockController::class, 'update'])
            ->name('products.stock.update');
        Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
            ->scopeBindings()
            ->name('products.images.destroy');

        Route::resource('orders', AdminOrderController::class)->only(['index', 'show']);
        Route::patch('/orders/{order}/status', [OrderStatusController::class, 'update'])
            ->name('orders.status.update');

        Route::resource('users', UserController::class)->only(['index', 'show']);
        Route::patch('/users/{user}/status', [UserStatusController::class, 'update'])
            ->name('users.status.update');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });
});
