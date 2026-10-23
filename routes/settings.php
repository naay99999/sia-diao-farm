<?php

use App\Http\Controllers\Settings\MemberController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::put('settings/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::middleware('can:manage-members')->group(function () {
        Route::get('settings/members', [MemberController::class, 'index'])->name('members.index');
        Route::post('settings/members', [MemberController::class, 'store'])->name('members.store');
        Route::patch('settings/members/{user}', [MemberController::class, 'update'])->name('members.update');
        Route::put('settings/members/{user}/password', [MemberController::class, 'resetPassword'])
            ->middleware('throttle:6,1')
            ->name('members.password');
        Route::patch('settings/members/{user}/deactivate', [MemberController::class, 'deactivate'])->name('members.deactivate');
        Route::patch('settings/members/{user}/activate', [MemberController::class, 'activate'])->name('members.activate');
    });

    Route::redirect('settings/ai', '/assistant');
});
