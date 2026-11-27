<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Notes;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\ContactNoteResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Note\GetContactNotesHandler;
use Illuminate\Http\JsonResponse;

class GetContactNotesAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetContactNotesHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $contactId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->resourceResponse(ContactNoteResource::class, $this->handler->handle($organizerId, $contactId));
    }
}
