<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Auth\Contracts\Data\PasskeyData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;

class PasskeyController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return Features::optionEnabled(Features::passkeys(), 'confirmPassword')
            ? [new Middleware('password.confirm', only: ['index'])]
            : [];
    }

    public function index(Request $request): Response
    {
        abort_unless(Features::canManagePasskeys(), 403);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/passkeys', [
            'passkeys' => $user->passkeys()
                ->latest()
                ->get()
                ->map(fn (Passkey $passkey) => PasskeyData::fromModel($passkey)),
        ]);
    }
}
