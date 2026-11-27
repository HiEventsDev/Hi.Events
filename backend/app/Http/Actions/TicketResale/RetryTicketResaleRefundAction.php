<?php

namespace HiEvents\Http\Actions\TicketResale;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\TicketResale\TicketResaleListingResource;
use HiEvents\Services\Application\Handlers\TicketResale\DTO\TicketResaleListingActionDTO;
use HiEvents\Services\Application\Handlers\TicketResale\RetryTicketResaleRefundHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

#[RequiresPermission(Permission::ISSUE_REFUNDS)]
class RetryTicketResaleRefundAction extends BaseAction
{
    public function __construct(
        private readonly RetryTicketResaleRefundHandler $handler,
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
