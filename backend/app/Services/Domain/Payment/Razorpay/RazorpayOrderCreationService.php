<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\Exceptions\Razorpay\CreateOrderFailedException;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderRequestDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderResponseDTO;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use Illuminate\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Razorpay\Api\Errors\Error;
use Throwable;

class RazorpayOrderCreationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Repository $config,
        private readonly ConnectionInterface $dbConnection,
        private readonly OrderApplicationFeeCalculationService $orderApplicationFeeCalculationService,
        private readonly RazorpayClientFactory $razorpayClientFactory,
    ) {
    }

    /**
     * @throws CreateOrderFailedException
     * @throws Throwable
     */
    public function createOrder(CreateRazorpayOrderRequestDTO $orderDTO): CreateRazorpayOrderResponseDTO
    {
        try {
            $this->dbConnection->beginTransaction();

            $razorpayClient = $this->razorpayClientFactory->create();

            // Calculate application fee for Razorpay
            // $applicationFee = $this->orderApplicationFeeCalculationService->calculateApplicationFee(
            //     accountConfiguration: $orderDTO->account->getConfiguration(),
            //     order: $orderDTO->order,
            //     vatSettings: $orderDTO->account->getAccountVatSetting(),
            // );

            // Razorpay amount is in paise (Indian) or smallest currency unit
            $amountInSmallestUnit = $orderDTO->amount->toMinorUnit();

            // For INR, amount is in paise
            // For other currencies, check Razorpay documentation for conversion
            if ($orderDTO->currencyCode !== 'INR') {
                // Razorpay supports multiple currencies but amounts might need different handling
                // This depends on Razorpay's currency requirements
                $amountInSmallestUnit = (int) round($orderDTO->amount->toFloat() * 100); // Default conversion
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

            // TODO: Fix for saas mode
            // if ($applicationFee && $this->config->get('services.razorpay.application_fee_enabled')) {
            //     $feeMinorUnit = $applicationFee->grossApplicationFee->toMinorUnit();

            //     $orderData['transfers'] = [
            //         [
            //             'account' => $this->config->get('services.razorpay.platform_account_id'),
            //             'amount'  => $feeMinorUnit,
            //             'currency' => $orderDTO->currencyCode,
            //             'notes'   => [
            //                 'order_id' => $orderDTO->order->getId(),
            //                 'type'     => 'application_fee'
            //             ],
            //         ]
            //     ];
            // }

            $razorpayOrder = $razorpayClient->createOrder($orderData);

            $this->logger->debug('Razorpay order created', [
                'razorpayOrderId' => $razorpayOrder->id,
                'orderDTO' => $orderDTO->toArray(['account']),
            ]);

            $this->dbConnection->commit();

            return new CreateRazorpayOrderResponseDTO(
                id: $razorpayOrder->id,
                keyId: $this->config->get('services.razorpay.key_id'),
                amount: $razorpayOrder->amount,
                currency: $razorpayOrder->currency,
                receipt: $razorpayOrder->receipt,
            );
        } catch (Error $exception) {
            $this->logger->error("Razorpay order creation failed: {$exception->getMessage()}", [
                'exception' => $exception,
                'orderDTO' => $orderDTO->toArray(['account']),
            ]);

            $this->dbConnection->rollBack();

            throw new CreateOrderFailedException(
                __('There was an error communicating with the payment provider. Please try again later.')
            );
        } catch (Throwable $exception) {
            $this->dbConnection->rollBack();

            throw $exception;
        }
    }
}