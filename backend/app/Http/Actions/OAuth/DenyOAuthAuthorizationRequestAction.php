<?php

namespace HiEvents\Http\Actions\OAuth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Services\Application\Handlers\OAuth\OAuthConsentHandler;
use Illuminate\Http\JsonResponse;

#[AllowedForReadOnlyUsers]
class DenyOAuthAuthorizationRequestAction extends BaseAction
{
    public function __construct(
        private readonly OAuthConsentHandler $handler,
    ) {}

    public function __invoke(string $requestId): JsonResponse
    {
        return $this->jsonResponse(['redirect_url' => $this->handler->deny($requestId)], wrapInData: true);
    }
}
