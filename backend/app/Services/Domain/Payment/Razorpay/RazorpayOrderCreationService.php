<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\Exceptions\Razorpay\CreateOrderFailedException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Domain\Order\DTO\ApplicationFeeValuesDTO;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderRequestDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderResponseDTO;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Razorpay\Api\Errors\Error;
use Throwable;

class RazorpayOrderCreationService
{
    public function __construct(
        private readonly LoggerInterface                       $logger,
        private readonly Repository                            $config,
        private readonly DatabaseManager                       $databaseManager,
        private readonly OrderApplicationFeeCalculationService $orderApplicationFeeCalculationService,
        private readonly RazorpayClientFactory                $razorpayClientFactory,
    )
    {
    }

    /**
     * @throws CreateOrderFailedException
     * @throws Throwable
     */
    public function createOrder(CreateRazorpayOrderRequestDTO $orderDTO): CreateRazorpayOrderResponseDTO
    {
        try {
            $this->databaseManager->beginTransaction();

            $razorpayClient = $this->razorpayClientFactory->create();

            // Calculate application fee for Razorpay
            $applicationFee = $this->orderApplicationFeeCalculationService->calculateApplicationFee(
                accountConfiguration: $orderDTO->account->getConfiguration(),
                order: $orderDTO->order,
                vatSettings: $orderDTO->account->getAccountVatSetting(),
            );

            // Razorpay amount is in paise (Indian) or smallest currency unit
            $amountInSmallestUnit = $orderDTO->amount->toMinorUnit();
            
            // For INR, amount is in paise
            // For other currencies, check Razorpay documentation for conversion
            if ($orderDTO->currencyCode !== 'INR') {
                // Razorpay supports multiple currencies but amounts might need different handling
                // This depends on Razorpay's currency requirements
                $amountInSmallestUnit = $orderDTO->amount->toFloat() * 100; // Default conversion
            }

            $orderData = [
                'amount' => $amountInSmallestUnit,
                'currency' => $orderDTO->currencyCode,
                'receipt' => $orderDTO->order->getShortId(),
                'payment_capture' => 1, // Auto-capture payment
                'notes' => [
                    'order_id' => $orderDTO->order->getId(),
                    'event_id' => $orderDTO->order->getEventId(),
                    'order_short_id' => $orderDTO->order->getShortId(),
                    'account_id' => $orderDTO->account->getId(),
                ],
            ];

            // Add application fee if applicable (Razorpay handles fees differently)
            if ($applicationFee && $this->config->get('services.razorpay.application_fee_enabled')) {
                $orderData['transfers'] = [
                    [
                        'account' => $this->config->get('services.razorpay.platform_account_id'),
                        'amount' => $applicationFee->grossApplicationFee->toMinorUnit(),
                        'currency' => $orderDTO->currencyCode,
                    ]
                ];
            }

            $razorpayOrder = $razorpayClient->order->create($orderData);

            $this->logger->debug('Razorpay order created', [
                'razorpayOrderId' => $razorpayOrder->id,
                'orderDTO' => $orderDTO->toArray(['account']),
            ]);

            $this->databaseManager->commit();

            return new CreateRazorpayOrderResponseDTO(
                id: $razorpayOrder->id,
                keyId: $this->config->get('services.razorpay.key_id'),
                amount: $razorpayOrder->amount,
                currency: $razorpayOrder->currency,
                receipt: $razorpayOrder->receipt,
            );
        } catch (Error $exception) {
            dd($exception);
            $this->logger->error("Razorpay order creation failed: {$exception->getMessage()}", [
                'exception' => $exception,
                'orderDTO' => $orderDTO->toArray(['account']),
            ]);

            $this->databaseManager->rollBack();

            throw new CreateOrderFailedException(
                __('There was an error communicating with the payment provider. Please try again later.')
            );
        } catch (Throwable $exception) {
            $this->databaseManager->rollBack();

            throw $exception;
        }
    }
}