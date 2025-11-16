<?php

use App\Domains\Token\Http\Controllers\TokenController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Organization token management
    Route::prefix('organizations/{organization:slug}/settings')->group(function () {
        Route::get('tokens', [TokenController::class, 'index'])->name('organizations.settings.tokens.index');
        Route::post('tokens', [TokenController::class, 'store'])->name('organizations.settings.tokens.store');
        Route::delete('tokens/{token}', [TokenController::class, 'destroy'])->name('organizations.settings.tokens.destroy');
    });
});

require __DIR__.'/settings.php';
