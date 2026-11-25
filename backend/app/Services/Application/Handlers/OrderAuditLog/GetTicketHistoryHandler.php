<?php

namespace HiEvents\Services\Application\Handlers\OrderAuditLog;

use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderAuditLogDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderAuditLogDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderAuditLogRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\OrderAuditLog\DTO\GetTicketHistoryDTO;
use Illuminate\Support\Collection;

class GetTicketHistoryHandler
{
    private const MAX_ENTRIES = 200;

    public function __construct(
        private readonly OrderAuditLogRepositoryInterface $orderAuditLogRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @return Collection<int, OrderAuditLogDomainObject>
     *
     * @throws ResourceNotFoundException
     */
    public function handle(GetTicketHistoryDTO $history): Collection
    {
        return $this->orderAuditLogRepository->findWhere(
            where: [
                OrderAuditLogDomainObjectAbstract::EVENT_ID => $history->event_id,
                ...$this->subjectCondition($history),
            ],
            orderAndDirections: [
                new OrderAndDirection(OrderAuditLogDomainObjectAbstract::CREATED_AT, OrderAndDirection::DIRECTION_DESC),
                new OrderAndDirection(OrderAuditLogDomainObjectAbstract::ID, OrderAndDirection::DIRECTION_DESC),
            ],
            limit: self::MAX_ENTRIES,
        );
    }

    /**
     * @return array<string, int>
     *
     * @throws ResourceNotFoundException
     */
    private function subjectCondition(GetTicketHistoryDTO $history): array
    {
        if ($history->attendee_id !== null) {
            $attendee = $this->attendeeRepository->findFirstWhere([
                AttendeeDomainObjectAbstract::ID => $history->attendee_id,
                AttendeeDomainObjectAbstract::EVENT_ID => $history->event_id,
            ]);

            if ($attendee === null) {
                throw new ResourceNotFoundException(__('Attendee not found'));
            }

            return [OrderAuditLogDomainObjectAbstract::ATTENDEE_ID => $history->attendee_id];
        }

        $order = $this->orderRepository->findFirstWhere([
            OrderDomainObjectAbstract::ID => $history->order_id,
            OrderDomainObjectAbstract::EVENT_ID => $history->event_id,
        ]);

        if ($order === null) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        return [OrderAuditLogDomainObjectAbstract::ORDER_ID => $history->order_id];
    }
}
