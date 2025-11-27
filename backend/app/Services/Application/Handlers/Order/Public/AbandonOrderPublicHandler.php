<?php

namespace HiEvents\Services\Application\Handlers\Order\Public;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Log\Logger;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class AbandonOrderPublicHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CheckoutSessionManagementService $sessionService,
        private readonly Logger $logger,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(string $orderShortId): OrderDomainObject
    {
        $order = $this->orderRepository->findByShortId($orderShortId);

        if (!$order) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        if ($order->getStatus() !== OrderStatus::RESERVED->name) {
            throw new ResourceConflictException(__('Order is not in a valid status to be abandoned'));
        }

        if ($order->isReservedOrderExpired()) {
            throw new ResourceConflictException(__('Order has already expired'));
        }

        $this->verifySessionId($order->getSessionId());

        $this->orderRepository->updateFromArray($order->getId(), [
            OrderDomainObjectAbstract::STATUS => OrderStatus::ABANDONED->name,
        ]);

        $this->logger->info('Order abandoned by customer', [
            'order_id' => $order->getId(),
            'order_short_id' => $orderShortId,
            'event_id' => $order->getEventId(),
        ]);

        return $this->orderRepository->findById($order->getId());
    }

    private function verifySessionId(string $orderSessionId): void
    {
        if (!$this->sessionService->verifySession($orderSessionId)) {
            throw new UnauthorizedException(
                __('Sorry, we could not verify your session. Please restart your order.')
            );
        }
    }
}
