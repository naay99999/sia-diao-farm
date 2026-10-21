<?php

use App\Http\Controllers\Farm\OrchardController;
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

    Route::post('orchards/{orchard}/trees', [TreeController::class, 'store'])->name('orchards.trees.store');
    Route::post('orchards/{orchard}/trees/batch', [TreeController::class, 'storeBatch'])->name('orchards.trees.batch');
    Route::get('trees/{tree}', [TreeController::class, 'show'])->name('trees.show');
    Route::patch('trees/{tree}', [TreeController::class, 'update'])->name('trees.update');
    Route::patch('trees/{tree}/remove', [TreeController::class, 'remove'])->name('trees.remove');
    Route::patch('trees/{tree}/restore', [TreeController::class, 'restore'])->name('trees.restore');
    Route::delete('trees/{tree}', [TreeController::class, 'destroy'])->name('trees.destroy');
    Route::get('t/{tree}', [TreeController::class, 'qr'])->name('trees.qr');
});

require __DIR__.'/settings.php';
