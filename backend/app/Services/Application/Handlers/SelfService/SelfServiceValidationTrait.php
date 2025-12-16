<?php

namespace HiEvents\Services\Application\Handlers\SelfService;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\SelfServiceDisabledException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

trait SelfServiceValidationTrait
{
    private function validateSelfServiceEnabled(EventDomainObject $event): void
    {
        if (!$event->getEventSettings()?->getAllowAttendeeSelfEdit()) {
            throw new SelfServiceDisabledException();
        }
    }

    /**
     * @throws SelfServiceDisabledException
     */
    private function loadAndValidateEvent(int $eventId): EventDomainObject
    {
        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($eventId);

        if (!$event) {
            throw new ResourceNotFoundException(__('Event not found'));
        }

        $this->validateSelfServiceEnabled($event);

        return $event;
    }

    private function loadAndValidateOrder(string $orderShortId, int $eventId): OrderDomainObject
    {
        $order = $this->orderRepository->findByShortId($orderShortId);

        if (!$order || $order->getEventId() !== $eventId) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        return $order;
    }
}
