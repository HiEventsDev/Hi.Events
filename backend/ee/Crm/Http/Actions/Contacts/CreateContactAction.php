<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Exceptions\DeletedContactRestoreRequiredException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\UpsertContactRequest;
use HiEvents\Enterprise\Crm\Resources\ContactResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\CreateContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CreateContactAction extends BaseCrmAction
{
    public function __construct(
        private readonly CreateContactHandler $handler,
    ) {}

    public function __invoke(UpsertContactRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $contact = $this->handler->handle(new UpsertContactDTO(
                account_id: $this->getAuthenticatedAccountId(),
                organizer_id: $organizerId,
                first_name: $request->validated('first_name'),
                last_name: $request->validated('last_name'),
                email: $request->validated('email'),
                restore_deleted: $request->boolean('restore_deleted'),
            ));
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['email' => $exception->getMessage()]);
        } catch (DeletedContactRestoreRequiredException $exception) {
            throw ValidationException::withMessages(['restore_deleted' => $exception->getMessage()]);
        }

        return $this->resourceResponse(ContactResource::class, $contact, Response::HTTP_CREATED);
    }
}
