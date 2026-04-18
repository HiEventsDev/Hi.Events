<?php

namespace HiEvents\Http\Actions\Contacts;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Contact\ContactResource;
use HiEvents\Services\Application\Handlers\Contact\GetContactHandler;
use Illuminate\Http\JsonResponse;

class GetContactAction extends BaseAction
{
    public function __construct(
        private readonly GetContactHandler $handler,
    ) {}

    public function __invoke(int $accountId, int $contactId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $contact = $this->handler->handle($contactId, $this->getAuthenticatedAccountId());

        return $this->resourceResponse(ContactResource::class, $contact);
    }
}
