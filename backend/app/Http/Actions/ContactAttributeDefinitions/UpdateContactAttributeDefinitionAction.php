<?php

namespace HiEvents\Http\Actions\ContactAttributeDefinitions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\ContactAttributeDefinition\UpdateContactAttributeDefinitionRequest;
use HiEvents\Resources\Contact\ContactAttributeDefinitionResource;
use HiEvents\Services\Application\Handlers\ContactAttributeDefinition\DTO\UpsertContactAttributeDefinitionDTO;
use HiEvents\Services\Application\Handlers\ContactAttributeDefinition\UpdateContactAttributeDefinitionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateContactAttributeDefinitionAction extends BaseAction
{
    public function __construct(
        private readonly UpdateContactAttributeDefinitionHandler $handler,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(UpdateContactAttributeDefinitionRequest $request, int $accountId, int $definitionId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        try {
            $definition = $this->handler->handle(
                $definitionId,
                UpsertContactAttributeDefinitionDTO::from(array_merge(
                    $request->validated(),
                    ['account_id' => $this->getAuthenticatedAccountId()],
                )),
            );
        } catch (ResourceConflictException $e) {
            throw ValidationException::withMessages([
                'name' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(ContactAttributeDefinitionResource::class, $definition);
    }
}
