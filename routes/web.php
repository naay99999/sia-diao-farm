<?php

use App\Http\Controllers\Farm\ActivityTypeController;
use App\Http\Controllers\Farm\CropCycleController;
use App\Http\Controllers\Farm\ExpenseCategoryController;
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
    Route::resource('activity-types', ActivityTypeController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::resource('expense-categories', ExpenseCategoryController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('plots', PlotController::class);

    Route::post('plots/{plot}/crop-cycles', [CropCycleController::class, 'store'])
        ->name('plots.crop-cycles.store');
    Route::patch('crop-cycles/{cropCycle}', [CropCycleController::class, 'update'])
        ->name('crop-cycles.update');
    Route::get('crop-cycles/{cropCycle}', [CropCycleController::class, 'show'])
        ->name('crop-cycles.show');
});

require __DIR__.'/settings.php';
