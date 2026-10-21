<?php

use App\Http\Controllers\Farm\OrchardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? to_route('orchards.index')
        : to_route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('orchards', OrchardController::class)
        ->except(['create', 'edit']);
});

require __DIR__.'/settings.php';
