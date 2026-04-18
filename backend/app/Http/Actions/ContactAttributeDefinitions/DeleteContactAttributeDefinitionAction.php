<?php

namespace HiEvents\Http\Actions\ContactAttributeDefinitions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\ContactAttributeDefinition\DeleteContactAttributeDefinitionHandler;
use Illuminate\Http\Response;

class DeleteContactAttributeDefinitionAction extends BaseAction
{
    public function __construct(
        private readonly DeleteContactAttributeDefinitionHandler $handler,
    ) {
    }

    /**
     * @throws ResourceConflictException
     */
    public function __invoke(int $accountId, int $definitionId): Response
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $this->handler->handle($definitionId, $this->getAuthenticatedAccountId());

        return $this->deletedResponse();
    }
}
