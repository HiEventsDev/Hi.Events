<?php

namespace HiEvents\Http\Actions\OAuth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Services\Application\Handlers\OAuth\OAuthConnectionsHandler;
use Illuminate\Http\Response;

#[AllowedForReadOnlyUsers]
class DeleteOAuthConnectionAction extends BaseAction
{
    public function __construct(
        private readonly OAuthConnectionsHandler $handler,
    ) {}

    public function __invoke(int $connectionId): Response
    {
        $this->handler->disconnect($connectionId, $this->getAuthenticatedUser()->getId(), $this->getAuthenticatedAccountId());

        return $this->deletedResponse();
    }
}
