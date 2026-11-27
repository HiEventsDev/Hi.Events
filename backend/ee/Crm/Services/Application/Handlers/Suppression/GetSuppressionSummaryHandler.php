<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression;

use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSuppressionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\DTO\SuppressionSummaryDTO;

class GetSuppressionSummaryHandler
{
    public function __construct(
        private readonly CrmSuppressionRepositoryInterface $suppressionRepository,
    ) {}

    public function handle(int $organizerId): SuppressionSummaryDTO
    {
        $counts = $this->suppressionRepository->countByReason($organizerId);

        return new SuppressionSummaryDTO(
            unsubscribed: $counts[CrmEmailMarketingStatus::UNSUBSCRIBED->value],
            bounced: $counts[CrmEmailMarketingStatus::BOUNCED->value],
            complained: $counts[CrmEmailMarketingStatus::COMPLAINED->value],
            deleted: $this->suppressionRepository->countSuppressedTombstones($organizerId),
        );
    }
}
