<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class HandleProviderCallbackAction extends BaseAuthAction
{
    public function __invoke(string $provider, Request $request): JsonResponse|RedirectResponse
    {
        $provider = strtolower($provider);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url') . '/login?error=provider_auth_failed');
        }

        $identifierKey = config("services.{$provider}.identifier_key", 'email');

        // Extract the identifier. Usually it's in the email property of Socialite user,
        // or inside the raw user array.
        $identifierValue = null;
        if ($identifierKey === 'email') {
            $identifierValue = $socialUser->getEmail();
        } else {
            $identifierValue = $socialUser->user[$identifierKey] ?? null;
        }

        if (!$identifierValue) {
            return redirect(config('app.frontend_url') . '/login?error=missing_identifier');
        }

        // Strictly check if the user exists
        $user = User::where($identifierKey, $identifierValue)->first();

        // 1. IF DOES NOT EXIST: Reject. Do not auto-provision.
        if (!$user) {
            return redirect(config('app.frontend_url') . '/login?error=user_not_registered');
        }

        // 2. IF EXISTS: Auto-verify if unverified
        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        // Generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Redirect to frontend auth handler
        return redirect(config('app.frontend_url') . '/login/callback?token=' . urlencode($token));
    }
}
