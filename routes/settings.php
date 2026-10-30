<?php

use App\Http\Controllers\Settings\AiConnectionController;
use App\Http\Controllers\Settings\FarmNoteController;
use App\Http\Controllers\Settings\FindingController;
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

    Route::get('settings/findings', [FindingController::class, 'index'])->name('findings.index');
    Route::post('settings/findings', [FindingController::class, 'store'])->name('findings.store');
    Route::patch('settings/findings/{finding}', [FindingController::class, 'update'])->name('findings.update');
    Route::delete('settings/findings/{finding}', [FindingController::class, 'destroy'])->name('findings.destroy');

    Route::get('settings/notes', [FarmNoteController::class, 'index'])->name('notes.index');
    Route::post('settings/notes', [FarmNoteController::class, 'store'])->name('notes.store');
    Route::patch('settings/notes/{note}', [FarmNoteController::class, 'update'])->name('notes.update');
    Route::delete('settings/notes/{note}', [FarmNoteController::class, 'destroy'])->name('notes.destroy');

    Route::get('settings/ai', [AiConnectionController::class, 'index'])->name('ai-connections.index');
    Route::delete('settings/ai/{user}/{client}', [AiConnectionController::class, 'destroy'])->name('ai-connections.destroy');
});
