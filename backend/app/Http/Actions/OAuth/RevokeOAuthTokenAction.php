<?php

namespace HiEvents\Http\Actions\OAuth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\OAuth\OAuthConnectionsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevokeOAuthTokenAction extends BaseAction
{
    use OAuthResponses;

    public function __construct(
        private readonly OAuthConnectionsHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->input('token');

        if (is_string($token) && $token !== '') {
            $this->handler->revokeToken($token);
        }

        return $this->noStore($this->jsonResponse([]));
    }
}
