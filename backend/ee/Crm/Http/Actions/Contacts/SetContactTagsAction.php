<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Exceptions\UnknownTagException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\SetContactTagsRequest;
use HiEvents\Enterprise\Crm\Resources\ContactResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SetContactTagsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SetContactTagsAction extends BaseCrmAction
{
    public function __construct(
        private readonly SetContactTagsHandler $handler,
    ) {}

    public function __invoke(SetContactTagsRequest $request, int $organizerId, int $contactId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $contact = $this->handler->handle(
                $organizerId,
                $contactId,
                array_map('intval', $request->validated('tag_ids') ?? []),
            );
        } catch (UnknownTagException $exception) {
            throw ValidationException::withMessages(['tag_ids' => $exception->getMessage()]);
        }

        return $this->resourceResponse(ContactResource::class, $contact);
    }
}
