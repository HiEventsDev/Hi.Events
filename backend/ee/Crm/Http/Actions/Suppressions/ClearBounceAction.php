<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Suppressions;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\ContactResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\GetContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\ClearBounceHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\DTO\ClearBounceDTO;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ClearBounceAction extends BaseCrmAction
{
    public function __construct(
        private readonly ClearBounceHandler $handler,
        private readonly GetContactHandler $contactHandler,
    ) {}

    public function __invoke(int $organizerId, int $contactId, int $contactEmailId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $this->handler->handle(new ClearBounceDTO(
                organizer_id: $organizerId,
                contact_id: $contactId,
                contact_email_id: $contactEmailId,
                user_id: $this->getAuthenticatedUser()->getId(),
            ));
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(ContactResource::class, $this->contactHandler->handle($organizerId, $contactId));
    }
}
