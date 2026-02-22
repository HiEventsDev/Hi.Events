<?php

namespace HiEvents\Http\Actions\Waitlist\Organizer;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Waitlist\CancelWaitlistEntryHandler;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CancelWaitlistEntryAction extends BaseAction
{
    public function __construct(
        private readonly CancelWaitlistEntryHandler $cancelWaitlistEntryHandler,
    )
    {
    }

    public function __invoke(int $eventId, int $entryId): Response|\Illuminate\Http\JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->cancelWaitlistEntryHandler->handleCancelById($entryId, $eventId);
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: SymfonyResponse::HTTP_NOT_FOUND,
            );
        } catch (ResourceConflictException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: SymfonyResponse::HTTP_CONFLICT,
            );
        }

        return $this->noContentResponse();
    }
}
