<?php

use App\Http\Controllers\Admin\PromoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::put('promos-first-order', [PromoController::class, 'firstOrderBanner'])->name('promos.first-order');
});
