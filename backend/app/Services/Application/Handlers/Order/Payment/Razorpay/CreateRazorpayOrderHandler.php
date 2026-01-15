<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\Razorpay\CreateOrderFailedException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderRequestDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderResponseDTO;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayOrderCreationService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use HiEvents\Values\MoneyValue;
use Razorpay\Api\Errors\Error;
use Throwable;

readonly class CreateRazorpayOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface         $orderRepository,
        private RazorpayOrderCreationService    $razorpayOrderService,
        private CheckoutSessionManagementService $sessionIdentifierService,
        private RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private AccountRepositoryInterface      $accountRepository,
    )
    {
    }

    /**
     * @throws CreateOrderFailedException
     * @throws MathException
     * @throws NumberFormatException
     * @throws RoundingNecessaryException
     * @throws UnknownCurrencyException
     * @throws Throwable
     */
    public function handle(string $orderShortId): CreateRazorpayOrderResponseDTO
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

        $account = $this->accountRepository
            ->loadRelation(new Relationship(
                domainObject: AccountConfigurationDomainObject::class,
                name: 'configuration',
            ))
            ->findByEventId($order->getEventId());

        // Check if we already have a Razorpay order
        if ($order->getRazorpayOrder() !== null) {
            return new CreateRazorpayOrderResponseDTO(
                id: $order->getRazorpayOrder()->getRazorpayOrderId(),
                keyId: config('services.razorpay.key_id'),
                amount: $order->getRazorpayOrder()->getAmount(),
                currency: $order->getRazorpayOrder()->getCurrency(),
            );
        }

        $razorpayOrder = $this->razorpayOrderService->createOrder(
            CreateRazorpayOrderRequestDTO::fromArray([
                'amount' => MoneyValue::fromFloat($order->getTotalGross(), $order->getCurrency()),
                'currencyCode' => $order->getCurrency(),
                'account' => $account,
                'order' => $order,
            ])
        );

        // Store Razorpay order in database
        $this->razorpayOrdersRepository->create([
            'order_id' => $order->getId(),
            'razorpay_order_id' => $razorpayOrder->id,
            'amount' => $razorpayOrder->amount,
            'currency' => strtoupper($order->getCurrency()),
            'receipt' => $order->getShortId(),
        ]);

        return new CreateRazorpayOrderResponseDTO(
            id: $razorpayOrder->id,
            keyId: config('services.razorpay.key_id'),
            amount: $razorpayOrder->amount,
            currency: $razorpayOrder->currency,
        );
    }
}