<?php

namespace HiEvents\Http\Actions\Contacts;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Contact\CreateContactRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Contact\ContactResource;
use HiEvents\Services\Application\Handlers\Contact\CreateContactHandler;
use HiEvents\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CreateContactAction extends BaseAction
{
    public function __construct(
        private readonly CreateContactHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(CreateContactRequest $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        try {
            $contact = $this->handler->handle(UpsertContactDTO::from(array_merge(
                $request->validated(),
                ['account_id' => $this->getAuthenticatedAccountId()],
            )));
        } catch (ResourceConflictException $e) {
            throw ValidationException::withMessages([
                'email' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            ContactResource::class,
            $contact,
            ResponseCodes::HTTP_CREATED,
        );
    }
}
