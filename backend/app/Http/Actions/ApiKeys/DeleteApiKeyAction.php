<?php

namespace HiEvents\Http\Actions\ApiKeys;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Services\Application\Handlers\ApiKey\DeleteApiKeyHandler;
use HiEvents\Services\Application\Handlers\ApiKey\DTO\DeleteApiKeyDTO;
use Illuminate\Http\Response;

#[AllowedForReadOnlyUsers]
class DeleteApiKeyAction extends BaseAction
{
    public function __construct(
        private readonly DeleteApiKeyHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(int $apiKeyId): Response
    {
        $this->handler->handle(new DeleteApiKeyDTO(
            apiKeyId: $apiKeyId,
            accountId: $this->getAuthenticatedAccountId(),
            userId: $this->getAuthenticatedUser()->getId(),
            canManageTeam: $this->getAuthenticatedAccess()->can(Permission::MANAGE_TEAM),
        ));

        return $this->deletedResponse();
    }
}
