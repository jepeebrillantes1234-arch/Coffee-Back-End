<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\GpsMonitorController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductOptionController;
use App\Http\Controllers\Admin\PromoController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\WebLoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $u = auth()->user();
    if (!$u) return redirect()->route('login');
    return redirect($u->role === 'admin' ? route('admin.dashboard') : '/staff');
});

Route::middleware('guest')->group(function () {
    Route::get('/login',  [WebLoginController::class, 'show'])->name('login');
    Route::post('/login', [WebLoginController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password/send-code', [PasswordResetController::class, 'sendCode'])->middleware('throttle:5,1')->name('password.request.code');
    Route::post('/forgot-password/verify-code', [PasswordResetController::class, 'verifyCode'])->middleware('throttle:5,1')->name('password.request.verify');
    Route::post('/forgot-password/change-email', [PasswordResetController::class, 'changeEmail'])->name('password.request.change-email');
});
Route::post('/logout', [WebLoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'show'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.reset.update');
Route::get('/password-reset-done', [PasswordResetController::class, 'done'])->name('password.reset.done');

// ================= ADMIN =================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Menu
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('products',   ProductController::class)->except('show');
    Route::get('products/{product}/options',         [ProductOptionController::class, 'index'])->name('products.options');
    Route::post('products/{product}/options/preset', [ProductOptionController::class, 'preset'])->name('products.options.preset');
    Route::post('products/{product}/groups',         [ProductOptionController::class, 'storeGroup'])->name('products.groups.store');
    Route::delete('groups/{group}',                  [ProductOptionController::class, 'destroyGroup'])->name('groups.destroy');
    Route::post('groups/{group}/options',            [ProductOptionController::class, 'storeOption'])->name('groups.options.store');
    Route::delete('options/{option}',                [ProductOptionController::class, 'destroyOption'])->name('options.destroy');

    // Promos + Settings
    Route::resource('promos', PromoController::class)->except('show');
    Route::get('settings',  [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings',  [SettingsController::class, 'update'])->name('settings.update');

    // Customers / Staff / Riders
    $roles = ['customer', 'staff', 'rider'];
    Route::get('users/{role}',          [UserController::class, 'index'])->whereIn('role', $roles)->name('users.index');
    Route::get('users/{role}/create',   [UserController::class, 'create'])->whereIn('role', ['staff', 'rider'])->name('users.create');
    Route::post('users/{role}',         [UserController::class, 'store'])->whereIn('role', ['staff', 'rider'])->name('users.store');
    Route::get('users/{user}/edit',     [UserController::class, 'edit'])->whereNumber('user')->name('users.edit');
    Route::put('users/{user}',          [UserController::class, 'update'])->whereNumber('user')->name('users.update');
    Route::post('users/{user}/toggle',  [UserController::class, 'toggle'])->whereNumber('user')->name('users.toggle');
    Route::post('users/{user}/approve', [UserController::class, 'approve'])->whereNumber('user')->name('users.approve');

    // Orders (kasama na ang workflow)
    Route::get('orders',                    [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}',            [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/confirm',   [OrderController::class, 'confirm'])->name('orders.confirm');
    Route::post('orders/{order}/reject',    [OrderController::class, 'reject'])->name('orders.reject');
    Route::post('orders/{order}/preparing', [OrderController::class, 'preparing'])->name('orders.preparing');
    Route::post('orders/{order}/ready',     [OrderController::class, 'ready'])->name('orders.ready');
    Route::post('orders/{order}/assign',    [OrderController::class, 'assign'])->name('orders.assign');
    Route::post('orders/{order}/complete',  [OrderController::class, 'complete'])->name('orders.complete');
    Route::post('orders/{order}/cancel',    [OrderController::class, 'cancel'])->name('orders.cancel');

    // Deliveries, GPS
    Route::get('deliveries',       [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::post('deliveries/{delivery}/remit', [DeliveryController::class, 'remit'])->name('deliveries.remit');
    Route::get('gps-monitor',      [GpsMonitorController::class, 'index'])->name('gps.index');
    Route::get('gps-monitor/data', [GpsMonitorController::class, 'data'])->name('gps.data');

    // Payments, Reviews
    Route::get('payments',                 [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/{order}/paid',   [PaymentController::class, 'markPaid'])->name('payments.paid');
    Route::post('payments/{order}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
    Route::get('reviews',                  [ReviewController::class, 'index'])->name('reviews.index');
    Route::post('reviews/{review}/toggle', [ReviewController::class, 'toggle'])->name('reviews.toggle');
    Route::put('reviews/{review}/reply',   [ReviewController::class, 'reply'])->name('reviews.reply');

    // Reports, Logs
    Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('activity-logs',  [ActivityLogController::class, 'index'])->name('activity.index');
});
if (is_file(__DIR__ . '/staff.php')) {
    require __DIR__ . '/staff.php';
}
require __DIR__ . '/promos_extra.php';


