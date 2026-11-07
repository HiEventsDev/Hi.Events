<?php

namespace HiEvents\Http\Actions\OAuth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\OAuth\OAuthConsentHandler;
use Illuminate\Http\JsonResponse;

class GetOAuthAuthorizationRequestAction extends BaseAction
{
    public function __construct(
        private readonly OAuthConsentHandler $handler,
    ) {}

    public function __invoke(string $requestId): JsonResponse
    {
        return $this->jsonResponse(
            $this->handler->show($requestId, $this->getAuthenticatedAccountId())->toArray(),
            wrapInData: true,
        );
    }
}
