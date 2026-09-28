<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless;

use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Cashless\DTO\CashlessSettingsDTO;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;

class GetCashlessSettingsHandler
{
    public function __construct(
        private readonly CashlessSettingsService $cashlessSettingsService,
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId): CashlessSettingsDTO
    {
        $settings = $this->cashlessSettingsService->getSettings($eventId);

        return new CashlessSettingsDTO(
            event_id: $eventId,
            cashless_enabled: $settings->getCashlessEnabled(),
            cashless_topup_product_id: $settings->getCashlessTopupProductId(),
            cashless_min_topup_amount: $settings->getCashlessMinTopupAmount(),
            cashless_allow_remaining_balance_refund: $settings->getCashlessAllowRemainingBalanceRefund(),
            cashless_refund_deadline_at: $settings->getCashlessRefundDeadlineAt(),
            cashless_online_topup_enabled: $settings->getCashlessOnlineTopupEnabled(),
            cashless_closed_at: $settings->getCashlessClosedAt(),
            cashless_topup_tax_and_fee_ids: $this->topupTaxAndFeeIds($settings->getCashlessTopupProductId()),
        );
    }

    private function topupTaxAndFeeIds(?int $topupProductId): array
    {
        if ($topupProductId === null) {
            return [];
        }

        $product = $this->productRepository
            ->loadRelation(TaxAndFeesDomainObject::class)
            ->findFirstWhere([ProductDomainObjectAbstract::ID => $topupProductId]);

        return ($product?->getTaxAndFees() ?? collect())
            ->map(fn (TaxAndFeesDomainObject $taxOrFee) => $taxOrFee->getId())
            ->values()
            ->toArray();
    }
}
