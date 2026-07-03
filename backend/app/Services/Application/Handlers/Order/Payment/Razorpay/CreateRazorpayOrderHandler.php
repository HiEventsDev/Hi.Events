<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\DomainObjects\Generated\RazorpayOrderDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\DTO\CreateRazorpayOrderResponseDTO;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayOrderCreationService;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayConfigurationService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Throwable;

class CreateRazorpayOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface         $orderRepository,
        private readonly RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private readonly RazorpayOrderCreationService     $razorpayOrderCreationService,
        private readonly CheckoutSessionManagementService $checkoutSessionManagementService,
        private readonly RazorpayConfigurationService     $razorpayConfigurationService,
    ) {
    }

    /**
     * @throws ResourceNotFoundException|Throwable
     */
    public function handle(string $orderShortId): CreateRazorpayOrderResponseDTO
    {
        $order = $this->orderRepository->findByShortId($orderShortId);

        if (!$order || !$this->checkoutSessionManagementService->verifySession($order->getSessionIdentifier())) {
            throw new ResourceNotFoundException('Order not found or invalid session');
        }

        // Check if a Razorpay order already exists for this order
        $existingRazorpayOrder = $this->razorpayOrdersRepository->findFirstWhere([
            RazorpayOrderDomainObjectAbstract::ORDER_ID => $order->getId(),
        ]);

        if ($existingRazorpayOrder) {
            $razorpayOrderId = $existingRazorpayOrder->getRazorpayOrderId();
            $amountMinor     = $existingRazorpayOrder->getAmountMinor();
            $currency        = $existingRazorpayOrder->getCurrency();
        } else {
            // Create a new one
            $result          = $this->razorpayOrderCreationService->createOrder($order);
            $razorpayOrderId = $result['razorpay_order_id'];
            $amountMinor     = $result['amount_minor'];
            $currency        = $result['currency'];
        }

        return new CreateRazorpayOrderResponseDTO(
            razorpay_order_id: $razorpayOrderId,
            key_id:            $this->razorpayConfigurationService->getKeyId() ?? '',
            amount_minor:      $amountMinor,
            currency:          $currency,
            prefill: [
                'name'  => $order->getFirstName() . ' ' . $order->getLastName(),
                'email' => $order->getEmail(),
            ],
        );
    }
}
