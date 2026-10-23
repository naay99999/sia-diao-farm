<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Farm\CareRecordController;
use App\Http\Controllers\Farm\ExpenseController;
use App\Http\Controllers\Farm\HarvestController;
use App\Http\Controllers\Farm\ObservationController;
use App\Http\Controllers\Farm\OrchardController;
use App\Http\Controllers\Farm\SaleController;
use App\Http\Controllers\Farm\SeasonController;
use App\Http\Controllers\Farm\TreeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? to_route('orchards.index')
        : to_route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('orchards', OrchardController::class)
        ->except(['create', 'edit']);

    Route::get('orchards/{orchard}/qr', [OrchardController::class, 'qr'])->name('orchards.qr');
    Route::post('orchards/{orchard}/seasons', [SeasonController::class, 'store'])->name('orchards.seasons.store');
    Route::get('seasons/{season}', [SeasonController::class, 'show'])->name('seasons.show');
    Route::patch('seasons/{season}', [SeasonController::class, 'update'])->name('seasons.update');
    Route::patch('seasons/{season}/close', [SeasonController::class, 'close'])->name('seasons.close');
    Route::delete('seasons/{season}', [SeasonController::class, 'destroy'])->name('seasons.destroy');

    Route::post('orchards/{orchard}/trees', [TreeController::class, 'store'])->name('orchards.trees.store');
    Route::post('orchards/{orchard}/trees/batch', [TreeController::class, 'storeBatch'])->name('orchards.trees.batch');
    Route::get('trees/{tree}', [TreeController::class, 'show'])->name('trees.show');
    Route::patch('trees/{tree}', [TreeController::class, 'update'])->name('trees.update');
    Route::patch('trees/{tree}/remove', [TreeController::class, 'remove'])->name('trees.remove');
    Route::patch('trees/{tree}/restore', [TreeController::class, 'restore'])->name('trees.restore');
    Route::delete('trees/{tree}', [TreeController::class, 'destroy'])->name('trees.destroy');
    Route::get('orchards/{orchard}/care-records/create', [CareRecordController::class, 'create'])->name('orchards.care-records.create');
    Route::post('orchards/{orchard}/care-records', [CareRecordController::class, 'store'])->name('orchards.care-records.store');
    Route::patch('care-records/{care_record}', [CareRecordController::class, 'update'])->name('care-records.update');
    Route::delete('care-records/{care_record}', [CareRecordController::class, 'destroy'])->name('care-records.destroy');
    Route::get('orchards/{orchard}/harvests/create', [HarvestController::class, 'create'])->name('orchards.harvests.create');
    Route::post('orchards/{orchard}/harvests', [HarvestController::class, 'storeBatch'])->name('orchards.harvests.batch');
    Route::post('trees/{tree}/harvests', [HarvestController::class, 'store'])->name('trees.harvests.store');
    Route::patch('harvests/{harvest}', [HarvestController::class, 'update'])->name('harvests.update');
    Route::delete('harvests/{harvest}', [HarvestController::class, 'destroy'])->name('harvests.destroy');
    Route::post('trees/{tree}/observations', [ObservationController::class, 'store'])->name('trees.observations.store');
    Route::post('tree-issues/{tree_issue}/follow-ups', [ObservationController::class, 'storeFollowUp'])->name('tree-issues.follow-ups.store');
    Route::patch('observations/{observation}', [ObservationController::class, 'update'])->name('observations.update');
    Route::delete('observations/{observation}', [ObservationController::class, 'destroy'])->name('observations.destroy');
    Route::post('orchards/{orchard}/sales', [SaleController::class, 'store'])->name('orchards.sales.store');
    Route::patch('sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
    Route::delete('sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::patch('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::get('assistant', [AssistantController::class, 'index'])->name('assistant.index');
    Route::delete('assistant/connections/{user}/{client}', [AssistantController::class, 'destroy'])->name('assistant.connections.destroy');
    Route::get('t/{tree}', [TreeController::class, 'qr'])->name('trees.qr');
});

require __DIR__.'/settings.php';
