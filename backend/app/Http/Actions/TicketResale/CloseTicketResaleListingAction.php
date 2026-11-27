<?php

namespace HiEvents\Http\Actions\TicketResale;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\TicketResale\TicketResaleListingResource;
use HiEvents\Services\Application\Handlers\TicketResale\CloseTicketResaleListingHandler;
use HiEvents\Services\Application\Handlers\TicketResale\DTO\TicketResaleListingActionDTO;
use Illuminate\Http\JsonResponse;
use Throwable;

class CloseTicketResaleListingAction extends BaseAction
{
    public function __construct(
        private readonly CloseTicketResaleListingHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function __invoke(int $eventId, int $listingId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $listing = $this->handler->handle(new TicketResaleListingActionDTO(event_id: $eventId, listing_id: $listingId));
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(TicketResaleListingResource::class, $listing);
    }
}
