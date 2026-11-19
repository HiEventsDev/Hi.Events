<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Infrastructure\Phone\PhoneNumberNormalizer;
use Illuminate\Database\DatabaseManager;
use Throwable;

class EditOrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly DatabaseManager $databaseManager,
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {}

    /**
     * @param  string|null  $phone  null leaves the number unchanged, an empty string removes it
     *
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function editOrder(
        int $id,
        int $eventId,
        ?string $firstName,
        ?string $lastName,
        ?string $email,
        ?string $notes,
        ?string $phone = null,
    ): OrderDomainObject {
        return $this->databaseManager->transaction(function () use ($id, $firstName, $lastName, $email, $notes, $eventId, $phone) {
            $where = [
                'id' => $id,
                'event_id' => $eventId,
            ];

            $order = $this->orderRepository->findFirstWhere($where);

            if ($order === null) {
                throw new ResourceNotFoundException(__('Order not found'));
            }

            $normalizedPhone = $phone === null ? null : $this->phoneNumberNormalizer->toMobileE164($phone);
            $phoneChanged = $phone !== null && $normalizedPhone !== $order->getPhone();

            $this->orderRepository->updateWhere(
                attributes: [
                    ...array_filter([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'notes' => $notes,
                    ]),
                    'email' => $email,
                    ...($phoneChanged ? [
                        'phone' => $normalizedPhone,
                        'phone_country' => $this->phoneNumberNormalizer->countryOf($normalizedPhone),
                        'sms_opted_in_at' => null,
                    ] : []),
                ],
                where: $where,
            );

            $this->domainEventDispatcherService->dispatch(
                new OrderEvent(
                    type: DomainEventType::ORDER_UPDATED,
                    orderId: $id,
                ),
            );

            return $this->orderRepository->findFirstWhere($where);
        });
    }
}
