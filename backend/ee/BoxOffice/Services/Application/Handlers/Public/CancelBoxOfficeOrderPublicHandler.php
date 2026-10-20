<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use Carbon\Carbon;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Enums\BoxOfficeTender;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderLookupService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use Throwable;

class CancelBoxOfficeOrderPublicHandler
{
    public const VOID_WINDOW_MINUTES = 30;

    public function __construct(
        private readonly BoxOfficeOrderLookupService $orderLookupService,
        private readonly OrderCancelService $orderCancelService,
        private readonly OrderAuditLogService $orderAuditLogService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function handle(
        BoxOfficeDomainObject $boxOffice,
        string $orderShortId,
        string $operatorName,
        string $ipAddress,
        ?string $userAgent,
    ): OrderDomainObject {
        $order = $this->orderLookupService->findOrFail($boxOffice, $orderShortId);

        if (! $order->isOrderCompleted()) {
            throw new ResourceConflictException(__('Only completed sales can be voided'));
        }

        if ($order->getBoxOfficeTender() === BoxOfficeTender::CARD->value) {
            throw new ResourceConflictException(__('Card sales must be refunded by an organizer from the Orders page'));
        }

        $completedAt = $order->getBoxOfficeCompletedAt();

        if ($completedAt === null || Carbon::parse($completedAt)->diffInMinutes(now()) > self::VOID_WINDOW_MINUTES) {
            throw new ResourceConflictException(__('This sale can no longer be voided at the door. Ask an organizer to cancel it.'));
        }

        $this->orderCancelService->cancelOrder($order);

        $this->orderAuditLogService->logBoxOfficeAction(
            action: OrderAuditAction::BOX_OFFICE_ORDER_VOIDED,
            eventId: $order->getEventId(),
            orderId: $order->getId(),
            operatorName: $operatorName,
            details: ['tender' => $order->getBoxOfficeTender()],
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return $this->orderLookupService->findOrFail($boxOffice, $orderShortId);
    }
}
