<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\Razorpay\PaymentVerificationFailedException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentVerificationService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Throwable;

readonly class VerifyRazorpayPaymentHandler
{
    public function __construct(
        private OrderRepositoryInterface          $orderRepository,
        private RazorpayPaymentVerificationService $razorpayPaymentService,
        private CheckoutSessionManagementService  $sessionIdentifierService,
        private RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
    )
    {
    }

    /**
     * @throws PaymentVerificationFailedException
     * @throws Throwable
     */
    public function handle(string $orderShortId, array $paymentData): OrderDomainObject
    {
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
        $isValid = $this->razorpayPaymentService->verifyPaymentSignature($paymentData);

        if (!$isValid) {
            throw new PaymentVerificationFailedException(__('Payment verification failed. Please try again.'));
        }

        // Update Razorpay order with payment details
        $this->razorpayOrdersRepository->updateByOrderId($order->getId(), [
            'razorpay_payment_id' => $paymentData['razorpay_payment_id'],
            'razorpay_signature' => $paymentData['razorpay_signature'],
            'payment_status' => 'captured',
        ]);

        // Update order status to completed
        $order->setStatus(OrderStatus::COMPLETED->name);
        $order->setPaymentStatus('PAYMENT_RECEIVED');
        $this->orderRepository->updateFromArray($order->getId(), [
            'status' => $order->getStatus(),
            'payment_status' => $order->getPaymentStatus(),
        ]);

        return $order;
    }
}