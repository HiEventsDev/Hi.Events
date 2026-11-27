<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\ContactResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\GetContactHandler;
use Illuminate\Http\JsonResponse;

class GetContactAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetContactHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $contactId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->resourceResponse(ContactResource::class, $this->handler->handle($organizerId, $contactId));
    }
}
