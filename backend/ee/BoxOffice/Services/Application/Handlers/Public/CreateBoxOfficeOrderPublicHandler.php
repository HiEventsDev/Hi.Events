<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\CreateBoxOfficeOrderDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderCreationService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class CreateBoxOfficeOrderPublicHandler
{
    private const IDEMPOTENCY_TTL_HOURS = 24;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly BoxOfficeOrderCreationService $orderCreationService,
        private readonly DatabaseManager $databaseManager,
        private readonly Cache $cache,
        private readonly TransactionLockService $transactionLockService,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws ValidationException
     */
    public function handle(CreateBoxOfficeOrderDTO $data): OrderDomainObject
    {
        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($data->box_office->getEventId());

        $occurrenceId = $data->session->event_occurrence_id;
        $cacheKey = sprintf('box_office_order:%d:%s', $data->box_office->getId(), $data->idempotency_key);
        $requestHash = $this->requestHash($data, $occurrenceId);

        return $this->databaseManager->transaction(function () use ($data, $event, $occurrenceId, $cacheKey, $requestHash) {
            $this->transactionLockService->lockEvent($event->getId());

            $existing = $this->cache->get($cacheKey);
            $existingOrder = is_array($existing)
                ? $this->orderRepository
                    ->loadRelation(OrderItemDomainObject::class)
                    ->loadRelation(AttendeeDomainObject::class)
                    ->findFirst($existing['order_id'])
                : null;

            if ($existingOrder !== null && ($existingOrder->isOrderReserved() || $existingOrder->isOrderCompleted())) {
                if ($existing['hash'] !== $requestHash) {
                    throw new ResourceConflictException(__('This sale was already submitted with different items'));
                }

                return $existingOrder;
            }

            $order = $this->orderCreationService->create($data, $event, $occurrenceId);

            $this->cache->put(
                $cacheKey,
                ['order_id' => $order->getId(), 'hash' => $requestHash],
                now()->addHours(self::IDEMPOTENCY_TTL_HOURS),
            );

            return $order;
        });
    }

    private function requestHash(CreateBoxOfficeOrderDTO $data, ?int $occurrenceId): string
    {
        return hash('sha256', json_encode([
            'items' => $data->items->map(fn ($item) => $item->toArray())->values()->all(),
            'discount' => $data->discount?->toArray(),
            'buyer' => $data->buyer->toArray(),
            'questions' => $data->questions,
            'occurrence' => $occurrenceId,
        ]));
    }
}
