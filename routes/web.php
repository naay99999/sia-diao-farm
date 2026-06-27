<?php

use App\Http\Controllers\Farm\FruitTypeController;
use App\Http\Controllers\Farm\FruitVarietyController;
use App\Http\Controllers\Farm\PlotController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('fruit-types', FruitTypeController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::resource('fruit-varieties', FruitVarietyController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('plots', PlotController::class);
});

require __DIR__.'/settings.php';
