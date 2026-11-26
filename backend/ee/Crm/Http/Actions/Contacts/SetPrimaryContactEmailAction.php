<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\ContactResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SetPrimaryContactEmailHandler;
use Illuminate\Http\JsonResponse;

class SetPrimaryContactEmailAction extends BaseCrmAction
{
    public function __construct(
        private readonly SetPrimaryContactEmailHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $contactId, int $contactEmailId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        return $this->resourceResponse(ContactResource::class, $this->handler->handle($organizerId, $contactId, $contactEmailId));
    }
}
