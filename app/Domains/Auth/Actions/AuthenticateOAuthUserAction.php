<?php

namespace App\Domains\Auth\Actions;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;

class AuthenticateOAuthUserAction
{
    public function handle(User $user): RedirectResponse
    {
        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            // The existing Fortify challenge requires a guest session.
            Auth::logout();
            session()->regenerate();
            session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => true,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
