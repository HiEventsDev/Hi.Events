<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class HandleProviderCallbackAction extends BaseAuthAction
{
    private \PHPOpenSourceSaver\JWTAuth\JWTAuth $jwtAuth;

    public function __construct(\PHPOpenSourceSaver\JWTAuth\JWTAuth $jwtAuth)
    {
        $this->jwtAuth = $jwtAuth;
    }

    public function __invoke(string $provider, Request $request): JsonResponse|RedirectResponse
    {
        $provider = strtolower($provider);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Provider authentication failed'], 401);
        }

        $identifierKey = config("services.{$provider}.identifier_key", 'email');

        $identifierValue = null;
        if ($identifierKey === 'email') {
            $identifierValue = $socialUser->getEmail();
        } else {
            $identifierValue = $socialUser->user[$identifierKey] ?? null;
        }

        if (!$identifierValue) {
            return response()->json(['message' => 'Missing identifier from provider'], 400);
        }

        $user = User::where('email', $identifierValue)->first();

        if (!$user) {
            return response()->json(['message' => 'User not registered'], 403);
        }

        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        $accounts = $user->accounts;
        $accountId = null;

        // If user has exactly one account or explicitly requested one (not applicable for basic redirect yet)
        if ($accounts->count() === 1) {
            $accountId = $accounts->first()->id;
        }

        $token = null;

        if ($accountId !== null) {
            $currentAccount = $accounts->firstWhere('id', $accountId);
            $role = $currentAccount?->pivot?->role;

            $claims = ['account_id' => $accountId];
            if ($role) {
                $claims['role'] = $role;
            }

            $token = $this->jwtAuth->claims($claims)->fromUser($user);

            \HiEvents\Models\AccountUser::where('user_id', $user->id)
                ->where('account_id', $accountId)
                ->update(['last_login_at' => now()]);

            // auth login the web guard with the user so we can access it during request
            auth()->login($user);
        }

        return $this->respondWithToken($token, $accounts);
    }
}
