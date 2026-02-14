<?php

use App\Domains\Organization\Http\Controllers\GitCredentialController;
use App\Domains\Organization\Http\Controllers\MemberController;
use App\Domains\Organization\Http\Controllers\OrganizationController;
use App\Domains\Organization\Http\Controllers\SettingsController;
use App\Domains\Package\Http\Controllers\PackageController;
use App\Domains\Repository\Http\Controllers\Api\RepositorySuggestionController;
use App\Domains\Repository\Http\Controllers\RepositoryController;
use App\Domains\Repository\Http\Controllers\SyncRepositoryController;
use App\Domains\Repository\Http\Controllers\SyncWebhookController;
use App\Domains\Token\Http\Controllers\TokenController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Organizations
    Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');

    // Organization routes (with access tracking)
    Route::middleware('track.organization')->group(function () {
        Route::get('organizations/{organization:slug}', [OrganizationController::class, 'show'])->name('organizations.show');
        Route::delete('organizations/{organization:slug}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
        Route::get('organizations/{organization:slug}/packages', [PackageController::class, 'index'])->name('organizations.packages.index');
        Route::get('organizations/{organization:slug}/packages/{package:uuid}', [PackageController::class, 'show'])->name('organizations.packages.show');
        Route::get('organizations/{organization:slug}/repositories', [RepositoryController::class, 'index'])->name('organizations.repositories.index');
        Route::post('organizations/{organization:slug}/repositories', [RepositoryController::class, 'store'])->name('organizations.repositories.store');
        Route::get('organizations/{organization:slug}/repositories/suggest', [RepositorySuggestionController::class, 'index'])->name('organizations.repositories.suggest');
        Route::get('organizations/{organization:slug}/repositories/{repository:uuid}', [RepositoryController::class, 'show'])->name('organizations.repositories.show');
        Route::get('organizations/{organization:slug}/repositories/{repository:uuid}/edit', [RepositoryController::class, 'edit'])->name('organizations.repositories.edit');
        Route::patch('organizations/{organization:slug}/repositories/{repository:uuid}', [RepositoryController::class, 'update'])->name('organizations.repositories.update');
        Route::delete('organizations/{organization:slug}/repositories/{repository:uuid}', [RepositoryController::class, 'destroy'])->name('organizations.repositories.destroy');
        Route::post('organizations/{organization:slug}/repositories/{repository:uuid}/sync', SyncRepositoryController::class)->name('organizations.repositories.sync');
        Route::post('organizations/{organization:slug}/repositories/{repository:uuid}/webhook/sync', SyncWebhookController::class)->name('organizations.repositories.webhook.sync');

        // Organization settings
        Route::prefix('organizations/{organization:slug}/settings')->name('organizations.settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::get('general', [SettingsController::class, 'general'])->name('general');
            Route::patch('general', [SettingsController::class, 'update'])->name('update');

            Route::get('members', [MemberController::class, 'index'])->name('members');
            Route::post('members', [MemberController::class, 'store'])->name('members.store');
            Route::patch('members/{member}', [MemberController::class, 'update'])->name('members.update');
            Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

            Route::get('tokens', [TokenController::class, 'index'])->name('tokens.index');
            Route::post('tokens', [TokenController::class, 'store'])->name('tokens.store');
            Route::delete('tokens/{token}', [TokenController::class, 'destroy'])->name('tokens.destroy');

            Route::get('git-credentials', [GitCredentialController::class, 'index'])->name('git-credentials.index');
            Route::post('git-credentials', [GitCredentialController::class, 'store'])->name('git-credentials.store');
            Route::patch('git-credentials/{credential}', [GitCredentialController::class, 'update'])->name('git-credentials.update');
            Route::delete('git-credentials/{credential}', [GitCredentialController::class, 'destroy'])->name('git-credentials.destroy');
        });
    });
});

require __DIR__.'/settings.php';
