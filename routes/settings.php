<?php

use App\Http\Controllers\Settings\AiConnectionController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::put('settings/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/ai', [AiConnectionController::class, 'edit'])->name('ai-connection.edit');
    Route::delete('settings/ai/{client}', [AiConnectionController::class, 'destroy'])->name('ai-connection.destroy');
});
