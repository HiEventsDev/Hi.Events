<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Public;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Generated\CashlessTransactionDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\InsufficientCashlessBalanceException;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Cashless\DTO\CreateCashlessPurchaseDTO;
use HiEvents\Services\Domain\Cashless\CashlessPosOrderService;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointAccessService;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointCatalogueService;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;
use HiEvents\Services\Domain\Cashless\CashlessWalletResolveService;
use HiEvents\Services\Domain\Cashless\CashlessWalletService;
use HiEvents\Services\Domain\Cashless\DTO\CashlessTransactionItemDTO;
use HiEvents\Services\Domain\Cashless\DTO\RecordCashlessTransactionDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateCashlessPurchasePublicHandler
{
    public function __construct(
        private readonly CashlessSalesPointAccessService $salesPointAccessService,
        private readonly CashlessSettingsService $cashlessSettingsService,
        private readonly CashlessWalletResolveService $walletResolveService,
        private readonly CashlessSalesPointCatalogueService $catalogueService,
        private readonly CashlessPosOrderService $posOrderService,
        private readonly CashlessWalletService $walletService,
        private readonly CashlessTransactionRepositoryInterface $transactionRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws CashlessSalesPointAccessException
     * @throws CashlessNotEnabledException
     * @throws CashlessWalletUnavailableException
     * @throws InsufficientCashlessBalanceException
     * @throws ValidationException
     * @throws Throwable
     */
    public function handle(CreateCashlessPurchaseDTO $purchaseData): CashlessTransactionDomainObject
    {
        $salesPoint = $this->salesPointAccessService->resolveAuthorised(
            shortId: $purchaseData->sales_point_short_id,
            token: $purchaseData->session_token,
        );

        $this->cashlessSettingsService->getEnabledSettings($salesPoint->getEventId());

        $alreadyRecorded = $this->findByClientReference($salesPoint, $purchaseData->client_reference_id);

        if ($alreadyRecorded !== null) {
            return $alreadyRecorded;
        }

        $this->catalogueService->assertSellableAtSalesPoint($salesPoint, $purchaseData->items);

        $wallet = $this->walletResolveService->resolveByAttendeePublicId(
            eventId: $salesPoint->getEventId(),
            attendeePublicId: $purchaseData->attendee_public_id,
        );

        return $this->databaseManager->transaction(function () use ($purchaseData, $salesPoint, $wallet) {
            $order = $this->posOrderService->recordSale(
                eventId: $salesPoint->getEventId(),
                attendee: $wallet->getAttendee(),
                items: $purchaseData->items,
                locale: $purchaseData->locale,
            );

            return $this->walletService->record(new RecordCashlessTransactionDTO(
                wallet_id: $wallet->getId(),
                type: CashlessTransactionType::PURCHASE,
                positive_amount: $order->getTotalGross(),
                order_id: $order->getId(),
                sales_point_id: $salesPoint->getId(),
                client_reference_id: $purchaseData->client_reference_id,
                items: $this->ledgerItems($order),
            ));
        });
    }

    /**
     * @return Collection<CashlessTransactionItemDTO>
     */
    private function ledgerItems(OrderDomainObject $order): Collection
    {
        return $order->getOrderItems()->map(fn (OrderItemDomainObject $item) => new CashlessTransactionItemDTO(
            product_id: $item->getProductId(),
            product_price_id: $item->getProductPriceId(),
            product_title: $item->getItemName(),
            unit_price: $item->getPrice(),
            quantity: $item->getQuantity(),
            total: $item->getTotalGross(),
        ));
    }

    private function findByClientReference(
        CashlessSalesPointDomainObject $salesPoint,
        string $clientReferenceId,
    ): ?CashlessTransactionDomainObject {
        return $this->transactionRepository->findFirstWhere([
            CashlessTransactionDomainObjectAbstract::CASHLESS_SALES_POINT_ID => $salesPoint->getId(),
            CashlessTransactionDomainObjectAbstract::CLIENT_REFERENCE_ID => $clientReferenceId,
        ]);
    }
}
