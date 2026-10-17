<?php

namespace HiEvents\Http\Actions\ApiKeys;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\ApiKey\ApiKeyResource;
use HiEvents\Services\Application\Handlers\ApiKey\GetApiKeysHandler;
use HiEvents\Services\Domain\Auth\ApiKey\ApiKeyService;
use Illuminate\Http\JsonResponse;

class GetApiKeysAction extends BaseAction
{
    public function __construct(
        private readonly GetApiKeysHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->resourceResponse(
            ApiKeyResource::class,
            $this->handler->handle(
                accountId: $this->getAuthenticatedAccountId(),
                userId: $this->getAuthenticatedUser()->getId(),
                includeTeamKeys: $this->getAuthenticatedAccess()->can(Permission::MANAGE_TEAM),
            ),
            meta: [
                'expiry_options_in_days' => ApiKeyService::EXPIRY_OPTIONS_IN_DAYS,
                'max_active_keys' => ApiKeyService::MAX_ACTIVE_KEYS_PER_MEMBER,
            ],
        );
    }
}
