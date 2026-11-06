<?php

namespace HiEvents\Http\Actions\OAuth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\OAuth\OAuthConnectionResource;
use HiEvents\Services\Application\Handlers\OAuth\OAuthConnectionsHandler;
use Illuminate\Http\JsonResponse;

class GetOAuthConnectionsAction extends BaseAction
{
    public function __construct(
        private readonly OAuthConnectionsHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->resourceResponse(
            OAuthConnectionResource::class,
            $this->handler->list($this->getAuthenticatedUser()->getId(), $this->getAuthenticatedAccountId()),
        );
    }
}
