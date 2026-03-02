<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Services\Domain\Auth\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class HandleProviderCallbackAction extends BaseAuthAction
{
    private LoginService $loginService;

    public function __construct(LoginService $loginService)
    {
        $this->loginService = $loginService;
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

        try {
            $loginResponse = $this->loginService->authenticateOidc(
                email: $identifierValue,
                requestedAccountId: null
            );
        } catch (UnauthorizedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return $this->respondWithToken($loginResponse->token, $loginResponse->accounts);
    }
}
