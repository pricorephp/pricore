<?php

use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MirrorController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Controllers\Api\V1\PackageVersionController;
use App\Http\Controllers\Api\V1\RepositoryController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.token', 'throttle:api'])->group(function () {
    // Authenticated user (personal access tokens only)
    Route::get('user', [UserController::class, 'show'])->name('user.show');
    Route::get('user/organizations', [UserController::class, 'organizations'])
        ->middleware('scope:read:organizations')->name('user.organizations');

    Route::get('user/tokens', [UserTokenController::class, 'index'])
        ->middleware('scope:read:tokens')->name('user.tokens.index');
    Route::post('user/tokens', [UserTokenController::class, 'store'])
        ->middleware('scope:write:tokens')->name('user.tokens.store');
    Route::patch('user/tokens/{token}', [UserTokenController::class, 'update'])
        ->middleware('scope:write:tokens')->name('user.tokens.update');
    Route::delete('user/tokens/{token}', [UserTokenController::class, 'destroy'])
        ->middleware('scope:write:tokens')->name('user.tokens.destroy');

    // Organizations (top level)
    Route::get('organizations', [OrganizationController::class, 'index'])
        ->middleware('scope:read:organizations')->name('organizations.index');
    Route::post('organizations', [OrganizationController::class, 'store'])
        ->middleware('scope:write:organizations')->name('organizations.store');

    // Organization-scoped resources
    Route::prefix('organizations/{organization:slug}')
        ->middleware('organization.member')
        ->group(function () {
            Route::get('/', [OrganizationController::class, 'show'])
                ->middleware('scope:read:organizations')->name('organizations.show');
            Route::patch('/', [OrganizationController::class, 'update'])
                ->middleware('scope:write:organizations')->name('organizations.update');
            Route::delete('/', [OrganizationController::class, 'destroy'])
                ->middleware('scope:delete:organizations')->name('organizations.destroy');

            // Repositories
            Route::get('repositories', [RepositoryController::class, 'index'])
                ->middleware('scope:read:repositories')->name('repositories.index');
            Route::post('repositories', [RepositoryController::class, 'store'])
                ->middleware('scope:write:repositories')->name('repositories.store');
            Route::post('repositories/bulk', [RepositoryController::class, 'bulkStore'])
                ->middleware('scope:write:repositories')->name('repositories.bulk');
            Route::get('repositories/{repository:uuid}', [RepositoryController::class, 'show'])
                ->middleware('scope:read:repositories')->name('repositories.show');
            Route::post('repositories/{repository:uuid}/sync', [RepositoryController::class, 'sync'])
                ->middleware('scope:write:repositories')->name('repositories.sync');
            Route::delete('repositories/{repository:uuid}', [RepositoryController::class, 'destroy'])
                ->middleware('scope:delete:repositories')->name('repositories.destroy');

            // Packages
            Route::get('packages', [PackageController::class, 'index'])
                ->middleware('scope:read:packages')->name('packages.index');
            Route::get('packages/{package:uuid}', [PackageController::class, 'show'])
                ->middleware('scope:read:packages')->name('packages.show');
            Route::delete('packages/{package:uuid}', [PackageController::class, 'destroy'])
                ->middleware('scope:delete:packages')->name('packages.destroy');
            Route::get('packages/{package:uuid}/versions', [PackageVersionController::class, 'index'])
                ->middleware('scope:read:packages')->name('packages.versions.index');
            Route::delete('packages/{package:uuid}/versions/{version:uuid}', [PackageVersionController::class, 'destroy'])
                ->middleware('scope:delete:packages')->name('packages.versions.destroy');

            // Members
            Route::get('members', [MemberController::class, 'index'])
                ->middleware('scope:read:members')->name('members.index');
            Route::post('members', [MemberController::class, 'store'])
                ->middleware('scope:write:members')->name('members.store');
            Route::patch('members/{member:uuid}', [MemberController::class, 'update'])
                ->middleware('scope:write:members')->name('members.update');
            Route::delete('members/{member:uuid}', [MemberController::class, 'destroy'])
                ->middleware('scope:write:members')->name('members.destroy');

            // Invitations
            Route::get('invitations', [InvitationController::class, 'index'])
                ->middleware('scope:read:members')->name('invitations.index');
            Route::post('invitations/{invitation:uuid}/resend', [InvitationController::class, 'resend'])
                ->middleware('scope:write:members')->name('invitations.resend');
            Route::delete('invitations/{invitation:uuid}', [InvitationController::class, 'destroy'])
                ->middleware('scope:write:members')->name('invitations.destroy');

            // Access tokens (organization scoped)
            Route::get('tokens', [TokenController::class, 'index'])
                ->middleware('scope:read:tokens')->name('tokens.index');
            Route::post('tokens', [TokenController::class, 'store'])
                ->middleware('scope:write:tokens')->name('tokens.store');
            Route::patch('tokens/{token}', [TokenController::class, 'update'])
                ->middleware('scope:write:tokens')->name('tokens.update');
            Route::delete('tokens/{token}', [TokenController::class, 'destroy'])
                ->middleware('scope:write:tokens')->name('tokens.destroy');

            // Mirrors
            Route::get('mirrors', [MirrorController::class, 'index'])
                ->middleware('scope:read:mirrors')->name('mirrors.index');
            Route::post('mirrors', [MirrorController::class, 'store'])
                ->middleware('scope:write:mirrors')->name('mirrors.store');
            Route::get('mirrors/{mirror:uuid}', [MirrorController::class, 'show'])
                ->middleware('scope:read:mirrors')->name('mirrors.show');
            Route::post('mirrors/{mirror:uuid}/sync', [MirrorController::class, 'sync'])
                ->middleware('scope:write:mirrors')->name('mirrors.sync');
            Route::delete('mirrors/{mirror:uuid}', [MirrorController::class, 'destroy'])
                ->middleware('scope:delete:mirrors')->name('mirrors.destroy');
        });
});
