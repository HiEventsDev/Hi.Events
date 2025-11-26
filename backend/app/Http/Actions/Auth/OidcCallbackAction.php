<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use GuzzleHttp\Exception\GuzzleException;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Auth\AuthenticatedResponseResource;
use HiEvents\Services\Application\Handlers\Auth\DTO\AuthenticatedResponseDTO;
use HiEvents\Services\Infrastructure\Auth\OidcProviderService;
use HiEvents\Services\Infrastructure\Auth\OidcUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class OidcCallbackAction extends BaseAuthAction
{
    private const STATE_COOKIE_NAME = 'oidc_state';

    public function __construct(
        private readonly OidcProviderService $oidcProviderService,
        private readonly OidcUserService     $oidcUserService,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        if (!$this->oidcProviderService->isEnabled()) {
            return $this->errorResponse(
                message: __('OIDC login is not enabled.'),
                statusCode: ResponseCodes::HTTP_NOT_FOUND,
            );
        }

        $statePayload = $this->getStatePayload($request->cookie(self::STATE_COOKIE_NAME));
        $state = $request->string('state')->toString();
        $code = $request->string('code')->toString();

        if ($statePayload === null || $statePayload['state'] !== $state || $code === '') {
            return $this->errorResponse(
                message: __('Invalid login state. Please try again.'),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        try {
            $tokens = $this->oidcProviderService->exchangeCode($code);
            $idToken = $tokens['id_token'] ?? null;

            if (!$idToken) {
                throw new RuntimeException('Missing id_token in provider response');
            }

            $claims = $this->oidcProviderService->validateIdToken($idToken, $statePayload['nonce']);
            $user = $this->oidcUserService->findOrCreateUser($claims);
            $loginResponse = $this->oidcUserService->buildLoginResponse($user);

            auth('api')->setToken($loginResponse->token);
            auth('api')->setUser($user);

            $returnTo = $this->resolveReturnTo($statePayload['return_to'] ?? null);

            if ($request->expectsJson()) {
                $response = $this->jsonResponse(new AuthenticatedResponseResource(new AuthenticatedResponseDTO(
                    token: $loginResponse->token,
                    expiresIn: auth('api')->factory()->getTTL() * 60,
                    accounts: $loginResponse->accounts,
                    user: $loginResponse->user,
                )));

                return $this->addTokenToResponse($response, $loginResponse->token)
                    ->withCookie($this->forgetStateCookie());
            }

            $redirect = redirect()->to($returnTo);

            return $this->addTokenToResponse($redirect, $loginResponse->token)
                ->withCookie($this->forgetStateCookie());
        } catch (GuzzleException|RuntimeException $exception) {
            Log::error('OIDC callback failed', ['message' => $exception->getMessage()]);

            return $this->errorResponse(
                message: __('Unable to authenticate with your identity provider. Please try again.'),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            )->withCookie($this->forgetStateCookie());
        }
    }

    private function getStatePayload(?string $cookie): ?array
    {
        if (!$cookie) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($cookie), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
    }

    private function forgetStateCookie(): SymfonyCookie
    {
        return Cookie::forget(self::STATE_COOKIE_NAME, path: '/', domain: null, secure: true, sameSite: 'none');
    }

    private function resolveReturnTo(?string $requested): string
    {
        $default = config('app.frontend_url', '/');

        if ($requested === null || $requested === '') {
            return $default;
        }

        if (str_starts_with($requested, '/')) {
            return $requested;
        }

        $frontendHost = parse_url($default, PHP_URL_HOST);
        $requestedHost = parse_url($requested, PHP_URL_HOST);

        if ($frontendHost !== null && $requestedHost === $frontendHost) {
            return $requested;
        }

        return $default;
    }
}
