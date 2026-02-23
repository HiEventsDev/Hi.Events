<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderApplicationFeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\Razorpay\PaymentVerificationFailedException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\VerifyRazorpayPaymentDTO;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentVerificationService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Log\Logger;
use Throwable;

readonly class VerifyRazorpayPaymentHandler
{
    public function __construct(
        private OrderRepositoryInterface           $orderRepository,
        private RazorpayPaymentVerificationService $razorpayPaymentService,
        private CheckoutSessionManagementService   $sessionIdentifierService,
        private RazorpayOrdersRepositoryInterface  $razorpayOrdersRepository,
        private AttendeeRepositoryInterface        $attendeeRepository,
        private ProductQuantityUpdateService       $quantityUpdateService,
        private AffiliateRepositoryInterface       $affiliateRepository,
        private DatabaseManager                    $databaseManager,
        private Logger                             $logger,
        private Repository                         $cache,
        private DomainEventDispatcherService       $domainEventDispatcherService,
        private OrderApplicationFeeService         $orderApplicationFeeService,
    ) {
    }

    /**
     * @throws PaymentVerificationFailedException
     * @throws Throwable
     */
    public function handle(string $orderShortId, VerifyRazorpayPaymentDTO $verifyRazorpayPaymentData): OrderDomainObject
    {
        // Check for duplicate processing
        if ($this->hasPaymentBeenHandled($verifyRazorpayPaymentData)) {
            $this->logger->info('Razorpay payment already handled', [
                'razorpay_payment_id' => $verifyRazorpayPaymentData->razorpay_payment_id,
                'order_short_id' => $orderShortId,
            ]);
            
            // Still return the order for user feedback
            return $this->orderRepository
                ->loadRelation(new Relationship(OrderItemDomainObject::class))
                ->findByShortId($orderShortId);
        }

        return $this->databaseManager->transaction(function () use ($orderShortId, $verifyRazorpayPaymentData) {
            // Load order with necessary relations
            $order = $this->orderRepository
                ->loadRelation(new Relationship(OrderItemDomainObject::class))
                ->loadRelation(new Relationship(RazorpayOrderDomainObject::class, name: 'razorpay_order'))
                ->findByShortId($orderShortId);

            if (!$order || !$this->sessionIdentifierService->verifySession($order->getSessionId())) {
                throw new UnauthorizedException(__('Sorry, we could not verify your session. Please create a new order.'));
            }

            if ($order->getStatus() !== OrderStatus::RESERVED->name || $order->isReservedOrderExpired()) {
                throw new ResourceConflictException(__('Sorry, is expired or not in a valid state.'));
            }

            // Verify the payment signature
            $isValid = $this->razorpayPaymentService->verifyPaymentSignature($verifyRazorpayPaymentData);

            if (!$isValid) {
                throw new PaymentVerificationFailedException(__('Payment verification failed. Please try again.'));
            }

            // Update Razorpay order with payment details
            $this->razorpayOrdersRepository->updateByOrderId($order->getId(), [
                'razorpay_payment_id' => $verifyRazorpayPaymentData->razorpay_payment_id,
                'razorpay_signature' => $verifyRazorpayPaymentData->razorpay_signature,
                'payment_status' => 'captured',
            ]);

            // Fetch complete payment details from Razorpay API
            $paymentDetails = $this->razorpayPaymentService->fetchPaymentDetails(
                $verifyRazorpayPaymentData->razorpay_payment_id
            );

            // Update order status to completed - THIS RETURNS THE UPDATED ORDER WITH ITEMS LOADED
            $updatedOrder = $this->updateOrderStatuses($order);
            
            // Update attendee statuses
            $this->updateAttendeeStatuses($updatedOrder);
            
            // Update product quantities - USE THE UPDATED ORDER WITH ITEMS LOADED
            $this->quantityUpdateService->updateQuantitiesFromOrder($updatedOrder);
            
            // Update affiliate sales
            $this->updateAffiliateSales($updatedOrder);
            
            // Store application fee
            $this->storeApplicationFeePayment($updatedOrder, $paymentDetails);
            
            // Dispatch events
            $this->dispatchEvents($updatedOrder);
            
            // Mark payment as handled
            $this->markPaymentAsHandled($verifyRazorpayPaymentData, $updatedOrder);

            return $updatedOrder;
        });
    }

    private function updateOrderStatuses(OrderDomainObject $order): OrderDomainObject
    {
        // IMPORTANT: Load OrderItemDomainObject relation when updating, just like Stripe handler does
        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->updateFromArray($order->getId(), [
                OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_RECEIVED->name,
                OrderDomainObjectAbstract::PAYMENT_PROVIDER => PaymentProviders::RAZORPAY->value,
            ]);
    }

    private function updateAttendeeStatuses(OrderDomainObject $order): void
    {
        $this->attendeeRepository->updateWhere(
            attributes: [
                'status' => AttendeeStatus::ACTIVE->name,
            ],
            where: [
                'order_id' => $order->getId(),
                'status' => AttendeeStatus::AWAITING_PAYMENT->name,
            ],
        );
    }

    private function updateAffiliateSales(OrderDomainObject $order): void
    {
        if ($order->getAffiliateId()) {
            $this->affiliateRepository->incrementSales(
                affiliateId: $order->getAffiliateId(),
                amount: $order->getTotalGross()
            );
        }
    }

    private function storeApplicationFeePayment(OrderDomainObject $order, array $paymentDetails): void
    {
        $feeAmount = $paymentDetails['fee'] ?? 0; // Fee in paise
        
        $this->orderApplicationFeeService->createOrderApplicationFee(
            orderId: $order->getId(),
            applicationFeeAmountMinorUnit: $feeAmount,
            orderApplicationFeeStatus: OrderApplicationFeeStatus::PAID,
            paymentMethod: PaymentProviders::RAZORPAY,
            currency: $order->getCurrency(),
        );
    }

    private function dispatchEvents(OrderDomainObject $order): void
    {
        // Dispatch Laravel event
        OrderStatusChangedEvent::dispatch($order);

        // Dispatch domain event
        $this->domainEventDispatcherService->dispatch(
            new OrderEvent(
                type: DomainEventType::ORDER_CREATED,
                orderId: $order->getId()
            )
        );
    }

    private function hasPaymentBeenHandled(VerifyRazorpayPaymentDTO $verifyRazorpayPaymentData): bool
    {
        $paymentId = $verifyRazorpayPaymentData->razorpay_payment_id ?? null;
        if (!$paymentId) {
            return false;
        }
        
        return $this->cache->has('razorpay_payment_handled_' . $paymentId);
    }

    private function markPaymentAsHandled(VerifyRazorpayPaymentDTO $verifyRazorpayPaymentData, OrderDomainObject $order): void
    {
        $this->logger->info('Razorpay payment verification handled', [
            'razorpay_payment_id' => $verifyRazorpayPaymentData->razorpay_payment_id,
            'order_id' => $order->getId(),
            'amount' => $order->getTotalGross(),
            'currency' => $order->getCurrency(),
        ]);

        $this->cache->put(
            'razorpay_payment_handled_' . $verifyRazorpayPaymentData->razorpay_payment_id,
            true,
            now()->addHours(24)
        );
    }
}