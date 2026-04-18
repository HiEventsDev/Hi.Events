<?php

namespace HiEvents\Http\Actions\ContactAttributeDefinitions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\ContactAttributeDefinition\CreateContactAttributeDefinitionRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Contact\ContactAttributeDefinitionResource;
use HiEvents\Services\Application\Handlers\ContactAttributeDefinition\CreateContactAttributeDefinitionHandler;
use HiEvents\Services\Application\Handlers\ContactAttributeDefinition\DTO\UpsertContactAttributeDefinitionDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CreateContactAttributeDefinitionAction extends BaseAction
{
    public function __construct(
        private readonly CreateContactAttributeDefinitionHandler $handler,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(CreateContactAttributeDefinitionRequest $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        try {
            $definition = $this->handler->handle(UpsertContactAttributeDefinitionDTO::from(array_merge(
                $request->validated(),
                ['account_id' => $this->getAuthenticatedAccountId()],
            )));
        } catch (ResourceConflictException $e) {
            throw ValidationException::withMessages([
                'name' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            ContactAttributeDefinitionResource::class,
            $definition,
            ResponseCodes::HTTP_CREATED,
        );
    }
}
