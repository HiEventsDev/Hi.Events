<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Notes;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\CreateContactNoteRequest;
use HiEvents\Enterprise\Crm\Resources\ContactNoteResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Note\CreateContactNoteHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CreateContactNoteAction extends BaseCrmAction
{
    public function __construct(
        private readonly CreateContactNoteHandler $handler,
    ) {}

    public function __invoke(CreateContactNoteRequest $request, int $organizerId, int $contactId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        $user = $this->getAuthenticatedUser();

        return $this->resourceResponse(
            resource: ContactNoteResource::class,
            data: $this->handler->handle(
                organizerId: $organizerId,
                contactId: $contactId,
                userId: $user->getId(),
                authorName: $user->getFullName(),
                note: $request->validated('note'),
            ),
            statusCode: Response::HTTP_CREATED,
        );
    }
}
