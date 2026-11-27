<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\MergeContactsRequest;
use HiEvents\Enterprise\Crm\Resources\ContactResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\MergeContactsHandler;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MergeContactsAction extends BaseCrmAction
{
    public function __construct(
        private readonly MergeContactsHandler $handler,
    ) {}

    public function __invoke(MergeContactsRequest $request, int $organizerId, int $contactId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $contact = $this->handler->handle($organizerId, $contactId, (int) $request->validated('source_contact_id'));
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(ContactResource::class, $contact);
    }
}
