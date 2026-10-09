<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymongoWebhookController;
use App\Http\Controllers\Api\RiderController;
use Illuminate\Support\Facades\Route;

// Base URL: /api/v1
Route::prefix('v1')->group(function () {

    // Public
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login',    [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::get('categories',    [MenuController::class, 'categories']);
    Route::get('products',      [MenuController::class, 'products']);
    Route::get('products/{id}', [MenuController::class, 'show']);
    Route::get('products/{id}/reviews', [MenuController::class, 'reviews']);

    // Tinatawag ng PayMongo (may signature check sa loob)
    Route::post('webhooks/paymongo', PaymongoWebhookController::class);

    // Naka-login
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout',       [AuthController::class, 'logout']);
        Route::get('profile',            [AuthController::class, 'profile']);
        Route::put('profile',            [AuthController::class, 'updateProfile']);
        Route::delete('profile',         [AuthController::class, 'deleteAccount']);
        Route::post('profile/fcm-token', [AuthController::class, 'fcmToken']);

        // ===== CUSTOMER =====
        Route::middleware('role:customer')->group(function () {
            Route::get('app-config',        [HomeController::class, 'config']);
            Route::get('addresses',         [AddressController::class, 'index']);
            Route::post('addresses',        [AddressController::class, 'store']);
            Route::delete('addresses/{id}', [AddressController::class, 'destroy']);

            Route::get('favorites',                  [FavoriteController::class, 'index']);
            Route::post('favorites',                 [FavoriteController::class, 'store']);
            Route::delete('favorites/{productId}',   [FavoriteController::class, 'destroy']);

            Route::get('notifications',              [NotificationController::class, 'index']);
            Route::post('notifications/read-all',    [NotificationController::class, 'readAll']);

            Route::post('checkout/preview',            [OrderController::class, 'preview']);
            Route::post('orders',                      [OrderController::class, 'store'])->middleware('throttle:20,1');
            Route::get('orders',                       [OrderController::class, 'index']);
            Route::get('orders/{id}',                  [OrderController::class, 'show']);
            Route::post('orders/{id}/pay',             [OrderController::class, 'pay'])->middleware('throttle:20,1');
            Route::post('orders/{id}/payment/verify',  [OrderController::class, 'verify'])->middleware('throttle:60,1');
            Route::post('orders/{id}/cancel',          [OrderController::class, 'cancel']);
            Route::get('orders/{id}/tracking',         [OrderController::class, 'tracking']);
            Route::post('orders/{id}/review',          [OrderController::class, 'review']);
        });

        // ===== RIDER =====
        Route::middleware('role:rider')->prefix('rider')->group(function () {
            Route::get('deliveries',                 [RiderController::class, 'index']);
            Route::get('deliveries/{id}',            [RiderController::class, 'show']);
            Route::post('deliveries/{id}/accept',    [RiderController::class, 'accept']);
            Route::post('deliveries/{id}/decline',   [RiderController::class, 'decline']);
            Route::post('deliveries/{id}/pickup',    [RiderController::class, 'pickup']);
            Route::post('deliveries/{id}/start',     [RiderController::class, 'start']);
            Route::post('deliveries/{id}/delivered', [RiderController::class, 'delivered']);
            Route::get('history',                    [RiderController::class, 'history']);
            Route::put('availability',               [RiderController::class, 'availability']);
            Route::post('location',                  [RiderController::class, 'location'])->middleware('throttle:60,1');
        });
    });
});

