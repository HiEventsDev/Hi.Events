<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless;

use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\CashlessTopupDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\CashlessTopupStatus;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\CashlessTopupRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Cashless\DTO\CreateCashlessTopupDTO;
use HiEvents\Services\Application\Handlers\Order\CreateOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CreateOrderPublicDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\Cashless\CashlessOccurrenceResolver;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;
use HiEvents\Services\Domain\Cashless\CashlessWalletResolveService;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateCashlessTopupHandler
{
    public function __construct(
        private readonly CashlessSettingsService $cashlessSettingsService,
        private readonly CashlessWalletResolveService $walletResolveService,
        private readonly CashlessTopupRepositoryInterface $topupRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CashlessOccurrenceResolver $occurrenceResolver,
        private readonly CreateOrderHandler $createOrderHandler,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws CashlessNotEnabledException
     * @throws CashlessWalletUnavailableException
     * @throws ValidationException
     * @throws Throwable
     */
    public function handle(CreateCashlessTopupDTO $topupData): OrderDomainObject
    {
        $settings = $this->cashlessSettingsService->getEnabledSettings($topupData->event_id);

        if (! $settings->getCashlessOnlineTopupEnabled()) {
            throw new CashlessNotEnabledException(
                __('Online top-ups are turned off for this event. Please top up at a sales point.')
            );
        }

        $this->validateAmount($topupData->amount, $settings);

        $wallet = $this->walletResolveService->resolveByTicketReference(
            eventId: $topupData->event_id,
            ticketReference: $topupData->ticket_reference,
        );

        if (! $wallet->isActive()) {
            throw new CashlessWalletUnavailableException(
                __('This cashless wallet is not active.')
            );
        }

        $topupProduct = $this->getTopupProduct($settings);
        $occurrenceId = $this->occurrenceResolver->resolveForSale($topupData->event_id);

        return $this->databaseManager->transaction(function () use ($topupData, $wallet, $topupProduct, $occurrenceId) {
            $order = $this->createOrderHandler->handle(
                eventId: $topupData->event_id,
                createOrderPublicDTO: CreateOrderPublicDTO::fromArray([
                    'is_user_authenticated' => $topupData->is_user_authenticated,
                    'session_identifier' => $topupData->session_identifier,
                    'order_locale' => $topupData->order_locale,
                    'products' => new Collection([
                        new ProductOrderDetailsDTO(
                            product_id: $topupProduct->getId(),
                            quantities: new Collection([
                                new OrderProductPriceDTO(
                                    quantity: 1,
                                    price_id: $topupProduct->getProductPrices()->first()->getId(),
                                    price: $topupData->amount,
                                ),
                            ]),
                            event_occurrence_id: $occurrenceId,
                        ),
                    ]),
                ]),
            );

            $this->createTopup($wallet, $order, $topupData->amount);

            return $this->prefillBuyerFromTicket($order, $wallet);
        });
    }

    /**
     * @throws ValidationException
     */
    private function validateAmount(float $amount, EventSettingDomainObject $settings): void
    {
        if ($amount < $settings->getCashlessMinTopupAmount()) {
            throw ValidationException::withMessages([
                'amount' => __('The minimum top-up amount is :amount', [
                    'amount' => $settings->getCashlessMinTopupAmount(),
                ]),
            ]);
        }
    }

    /**
     * @throws CashlessNotEnabledException
     */
    private function getTopupProduct(EventSettingDomainObject $settings): ProductDomainObject
    {
        $product = $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->findFirstWhere([
                ProductDomainObjectAbstract::ID => $settings->getCashlessTopupProductId(),
            ]);

        if ($product?->getProductPrices()?->first() === null) {
            throw new CashlessNotEnabledException(
                __('Cashless top-ups are not available for this event.')
            );
        }

        return $product;
    }

    private function prefillBuyerFromTicket(
        OrderDomainObject $order,
        CashlessWalletDomainObject $wallet,
    ): OrderDomainObject {
        $attendee = $wallet->getAttendee();

        if ($attendee === null) {
            return $order;
        }

        return $this->orderRepository->updateFromArray($order->getId(), [
            OrderDomainObjectAbstract::FIRST_NAME => $attendee->getFirstName(),
            OrderDomainObjectAbstract::LAST_NAME => $attendee->getLastName(),
            OrderDomainObjectAbstract::EMAIL => $attendee->getEmail(),
        ]);
    }

    private function createTopup(CashlessWalletDomainObject $wallet, OrderDomainObject $order, float $amount): void
    {
        $this->topupRepository->create([
            CashlessTopupDomainObjectAbstract::EVENT_ID => $wallet->getEventId(),
            CashlessTopupDomainObjectAbstract::CASHLESS_WALLET_ID => $wallet->getId(),
            CashlessTopupDomainObjectAbstract::ORDER_ID => $order->getId(),
            CashlessTopupDomainObjectAbstract::AMOUNT => Currency::round($amount),
            CashlessTopupDomainObjectAbstract::STATUS => CashlessTopupStatus::PENDING->value,
        ]);
    }
}
