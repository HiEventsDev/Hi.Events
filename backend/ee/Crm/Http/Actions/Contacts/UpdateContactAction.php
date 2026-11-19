<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\UpsertContactRequest;
use HiEvents\Enterprise\Crm\Resources\ContactResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\UpdateContactHandler;
use Illuminate\Http\JsonResponse;

class UpdateContactAction extends BaseCrmAction
{
    public function __construct(
        private readonly UpdateContactHandler $handler,
    ) {}

    public function __invoke(UpsertContactRequest $request, int $organizerId, int $contactId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        return $this->resourceResponse(ContactResource::class, $this->handler->handle(new UpsertContactDTO(
            account_id: $this->getAuthenticatedAccountId(),
            organizer_id: $organizerId,
            first_name: $request->validated('first_name'),
            last_name: $request->validated('last_name'),
            contact_id: $contactId,
        )));
    }
}
