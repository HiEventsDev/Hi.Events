<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\Enterprise\Sso\Services\Domain\DTO\LinkableSsoConnection;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Resources\Auth\Sso\UserIdentityResource;
use HiEvents\Services\Application\Handlers\Auth\Sso\GetSsoIdentitiesHandler;
use Illuminate\Http\JsonResponse;

#[AllowedForReadOnlyUsers]
class GetSsoIdentitiesAction extends BaseAction
{
    public function __construct(
        private readonly GetSsoIdentitiesHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        $result = $this->handler->handle($this->getAuthenticatedUser()->getId());

        return $this->resourceResponse(
            UserIdentityResource::class,
            $result->identities,
            meta: [
                'linkable_connections' => $result->linkableConnections
                    ->map(fn (LinkableSsoConnection $connection) => [
                        'key' => $connection->key,
                        'name' => $connection->name,
                        'type' => $connection->type->value,
                        'logo_url' => null,
                        'account_name' => $connection->accountName,
                    ])
                    ->all(),
            ],
        );
    }
}
