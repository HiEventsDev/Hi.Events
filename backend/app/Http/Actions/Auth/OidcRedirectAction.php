<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Infrastructure\Auth\OidcProviderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class OidcRedirectAction extends BaseAction
{
    private const STATE_COOKIE_NAME = 'oidc_state';
    private const STATE_COOKIE_TTL_MINUTES = 10;

    public function __construct(
        private readonly OidcProviderService $oidcProviderService,
    )
    {
    }

    public function __invoke(Request $request)
    {
        if (!$this->oidcProviderService->isEnabled()) {
            return $this->errorResponse(
                message: __('OIDC login is not enabled.'),
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        $state = Str::random(32);
        $nonce = Str::random(32);
        $returnTo = $this->resolveReturnTo($request->string('return_to')->toString());

        $authorizationUrl = $this->oidcProviderService->getAuthorizationUrl($state, $nonce);

        return redirect()
            ->away($authorizationUrl)
            ->withCookie($this->makeStateCookie($state, $nonce, $returnTo));
    }

    private function makeStateCookie(string $state, string $nonce, string $returnTo): SymfonyCookie
    {
        $payload = Crypt::encryptString(json_encode([
            'state' => $state,
            'nonce' => $nonce,
            'return_to' => $returnTo,
        ], JSON_THROW_ON_ERROR));

        return Cookie::make(
            name: self::STATE_COOKIE_NAME,
            value: $payload,
            minutes: self::STATE_COOKIE_TTL_MINUTES,
            path: '/',
            domain: null,
            secure: true,
            httpOnly: true,
            raw: false,
            sameSite: 'none',
        );
    }

    private function resolveReturnTo(?string $requested): string
    {
        $default = config('app.frontend_url', '/');

        if ($requested === null || $requested === '') {
            return $default;
        }

        // Allow relative paths or same host URLs to avoid open redirects
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
