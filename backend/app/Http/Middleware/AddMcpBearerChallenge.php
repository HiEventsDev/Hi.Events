<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\DomainObjects\Enums\OAuthScope;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Domain\Auth\OAuth\OAuthEndpoints;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddMcpBearerChallenge
{
    public function __construct(
        private readonly OAuthEndpoints $endpoints,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== ResponseCodes::HTTP_UNAUTHORIZED) {
            return $response;
        }

        $parameters = [
            'resource_metadata="'.$this->endpoints->protectedResourceMetadataUrl().'"',
            'scope="'.implode(' ', OAuthScope::valuesArray()).'"',
        ];

        if ($request->bearerToken() !== null) {
            $parameters[] = 'error="invalid_token"';
        }

        $response->headers->set('WWW-Authenticate', 'Bearer '.implode(', ', $parameters));

        return $response;
    }
}
