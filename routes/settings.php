<?php

use App\Http\Controllers\Settings\ConnectGitHubController;
use App\Http\Controllers\Settings\LeaveOrganizationController;
use App\Http\Controllers\Settings\OrganizationsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\Settings\UserGitCredentialController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/organizations', [OrganizationsController::class, 'index'])->name('settings.organizations');
    Route::delete('settings/organizations/{organization:slug}', LeaveOrganizationController::class)->name('settings.organizations.leave');

    Route::get('settings/git-credentials', [UserGitCredentialController::class, 'index'])->name('settings.git-credentials');
    Route::post('settings/git-credentials', [UserGitCredentialController::class, 'store'])->name('settings.git-credentials.store');
    Route::patch('settings/git-credentials/{credential}', [UserGitCredentialController::class, 'update'])->name('settings.git-credentials.update');
    Route::delete('settings/git-credentials/{credential}', [UserGitCredentialController::class, 'destroy'])->name('settings.git-credentials.destroy');

    Route::get('settings/git-credentials/github/connect', [ConnectGitHubController::class, 'redirect'])->name('settings.github.connect');
    Route::get('settings/git-credentials/github/callback', [ConnectGitHubController::class, 'callback'])->name('settings.github.callback');
});
