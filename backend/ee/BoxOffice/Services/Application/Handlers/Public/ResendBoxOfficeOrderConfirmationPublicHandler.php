<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderLookupService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use Illuminate\Validation\ValidationException;

class ResendBoxOfficeOrderConfirmationPublicHandler
{
    public function __construct(
        private readonly BoxOfficeOrderLookupService $orderLookupService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly SendOrderDetailsService $sendOrderDetailsService,
        private readonly OrderAuditLogService $orderAuditLogService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws ValidationException
     */
    public function handle(
        BoxOfficeDomainObject $boxOffice,
        string $orderShortId,
        ?string $email,
        string $operatorName,
        string $ipAddress,
        ?string $userAgent,
    ): OrderDomainObject {
        $order = $this->orderLookupService->findOrFail($boxOffice, $orderShortId);

        if (! $order->isOrderCompleted()) {
            throw new ResourceConflictException(__('Tickets can only be sent for completed sales'));
        }

        if ($email !== null && $order->getEmail() !== null && $email !== $order->getEmail()) {
            throw new ResourceConflictException(__('Tickets for this sale were already sent to :email. Ask an organizer to change it.', [
                'email' => $order->getEmail(),
            ]));
        }

        if ($email !== null && $order->getEmail() === null) {
            $this->orderRepository->updateFromArray($order->getId(), [OrderDomainObjectAbstract::EMAIL => $email]);
            $this->attendeeRepository->updateWhere(
                attributes: [AttendeeDomainObjectAbstract::EMAIL => $email],
                where: [AttendeeDomainObjectAbstract::ORDER_ID => $order->getId()],
            );
            $this->orderAuditLogService->logBoxOfficeAction(
                action: OrderAuditAction::BOX_OFFICE_EMAIL_UPDATED,
                eventId: $order->getEventId(),
                orderId: $order->getId(),
                operatorName: $operatorName,
                details: ['email' => $email],
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
            $order = $this->orderLookupService->findOrFail($boxOffice, $orderShortId);
        }

        if ($order->getEmail() === null) {
            throw ValidationException::withMessages([
                'email' => __('Enter an email address to send the tickets to'),
            ]);
        }

        $this->sendOrderDetailsService->sendOrderSummaryAndTicketEmails($order);

        $this->orderAuditLogService->logEmailResent(
            action: OrderAuditAction::ORDER_EMAIL_RESENT->value,
            eventId: $order->getEventId(),
            orderId: $order->getId(),
            attendeeId: null,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return $order;
    }
}
