<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\StripeCustomerDomainObject;
use HiEvents\Exceptions\Stripe\CreatePaymentIntentFailedException;
use HiEvents\Repository\Interfaces\StripeCustomerRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentRequestDTO;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentResponseDTO;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripePaymentIntentCreationService
{
    public function __construct(
        private readonly LoggerInterface                       $logger,
        private readonly Repository                            $config,
        private readonly StripeCustomerRepositoryInterface     $stripeCustomerRepository,
        private readonly DatabaseManager                       $databaseManager,
        private readonly OrderApplicationFeeCalculationService $orderApplicationFeeCalculationService,
    )
    {
    }

    /**
     * @throws CreatePaymentIntentFailedException
     */
    public function retrievePaymentIntentClientSecretWithClient(
        StripeClient $stripeClient,
        string       $paymentIntentId,
        ?string      $accountId = null,
    ): string
    {
        try {
            return $stripeClient->paymentIntents->retrieve(
                id: $paymentIntentId,
                opts: $accountId ? ['stripe_account' => $accountId] : []
            )->client_secret;
        } catch (ApiErrorException $exception) {
            $this->logger->error("Stripe payment intent retrieval failed: {$exception->getMessage()}", [
                'exception' => $exception,
                'paymentIntentId' => $paymentIntentId,
            ]);

            throw new CreatePaymentIntentFailedException(
                __('There was an error communicating with the payment provider. Please try again later.')
            );
        }
    }

    /**
     * @throws CreatePaymentIntentFailedException
     * @throws ApiErrorException|Throwable
     */
    public function createPaymentIntentWithClient(
        StripeClient                  $stripeClient,
        CreatePaymentIntentRequestDTO $paymentIntentDTO
    ): CreatePaymentIntentResponseDTO
    {
        try {
            $this->databaseManager->beginTransaction();

            $applicationFee = $this->orderApplicationFeeCalculationService->calculateApplicationFee(
                accountConfiguration: $paymentIntentDTO->account->getConfiguration(),
                order: $paymentIntentDTO->order,
            )->toMinorUnit();

            $paymentIntent = $stripeClient->paymentIntents->create([
                'amount' => $paymentIntentDTO->amount->toMinorUnit(),
                'currency' => $paymentIntentDTO->currencyCode,
                'customer' => $this->upsertStripeCustomerWithClient($stripeClient, $paymentIntentDTO)->getStripeCustomerId(),
                'metadata' => [
                    'order_id' => $paymentIntentDTO->order->getId(),
                    'event_id' => $paymentIntentDTO->order->getEventId(),
                    'order_short_id' => $paymentIntentDTO->order->getShortId(),
                    'account_id' => $paymentIntentDTO->account->getId(),
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                $applicationFee ? ['application_fee_amount' => $applicationFee] : [],
            ], $this->getStripeAccountData($paymentIntentDTO));

            $this->logger->debug('Stripe payment intent created', [
                'paymentIntentId' => $paymentIntent->id,
                'paymentIntentDTO' => $paymentIntentDTO->toArray(['account']),
            ]);

            $this->databaseManager->commit();

            return new CreatePaymentIntentResponseDTO(
                paymentIntentId: $paymentIntent->id,
                clientSecret: $paymentIntent->client_secret,
                accountId: $paymentIntentDTO->stripeAccountId,
                applicationFeeAmount: $applicationFee,
            );
        } catch (ApiErrorException $exception) {
            $this->logger->error("Stripe payment intent creation failed: {$exception->getMessage()}", [
                'exception' => $exception,
                'paymentIntentDTO' => $paymentIntentDTO->toArray(['account']),
            ]);

            $this->databaseManager->rollBack();

            throw new CreatePaymentIntentFailedException(
                __('There was an error communicating with the payment provider. Please try again later.')
            );
        } catch (Throwable $exception) {
            $this->databaseManager->rollBack();

            throw $exception;
        }
    }

    /**
     * @throws CreatePaymentIntentFailedException
     */
    private function getStripeAccountData(CreatePaymentIntentRequestDTO $paymentIntentDTO): array
    {
        if (!$this->config->get('app.saas_mode_enabled')) {
            return [];
        }

        if ($paymentIntentDTO->stripeAccountId === null) {
            $this->logger->error(
                'Stripe Connect account not found for the event organizer, payment intent creation failed.
                You will need to connect your Stripe account to receive payments.',
                ['paymentIntentDTO' => $paymentIntentDTO->toArray(['account'])]
            );

            throw new CreatePaymentIntentFailedException(
                __('Stripe Connect account not found for the event organizer')
            );
        }

        return [
            'stripe_account' => $paymentIntentDTO->stripeAccountId
        ];
    }

    /**
     * @throws ApiErrorException|CreatePaymentIntentFailedException
     */
    private function upsertStripeCustomerWithClient(
        StripeClient                  $stripeClient,
        CreatePaymentIntentRequestDTO $paymentIntentDTO
    ): StripeCustomerDomainObject
    {
        $customer = $this->stripeCustomerRepository->findFirstWhere([
            'email' => $paymentIntentDTO->order->getEmail(),
            'stripe_account_id' => $paymentIntentDTO->stripeAccountId,
        ]);

        if ($customer === null) {
            $stripeCustomer = $stripeClient->customers->create(
                params: [
                    'email' => $paymentIntentDTO->order->getEmail(),
                    'name' => $paymentIntentDTO->order->getFullName(),
                ],
                opts: $this->getStripeAccountData($paymentIntentDTO)
            );

            return $this->stripeCustomerRepository->create([
                'name' => $stripeCustomer->name,
                'email' => $stripeCustomer->email,
                'stripe_customer_id' => $stripeCustomer->id,
                'stripe_account_id' => $paymentIntentDTO->stripeAccountId,
            ]);
        }

        if ($customer->getName() === $paymentIntentDTO->order->getFullName()) {
            return $customer;
        }

        $stripeCustomer = $stripeClient->customers->update(
            id: $customer->getStripeCustomerId(),
            params: ['name' => $paymentIntentDTO->order->getFullName()],
            opts: $this->getStripeAccountData($paymentIntentDTO),
        );

        $this->stripeCustomerRepository->updateFromArray($customer->getId(), [
            'name' => $stripeCustomer->name,
        ]);

        return $customer;
    }
}
