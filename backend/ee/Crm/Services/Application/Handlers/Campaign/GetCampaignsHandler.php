<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\DomainObjects\Enums\CrmCampaignSource;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignReportService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignSendGate;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCampaignsHandler
{
    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CampaignReportService $reportService,
        private readonly CampaignSendGate $sendGate,
    ) {}

    public function handle(int $organizerId, ?string $folder, ?CrmCampaignSource $source, ?string $search, QueryParamsDTO $params, bool $canViewFinancials): LengthAwarePaginator
    {
        $campaigns = $this->campaignRepository->findByOrganizerId($organizerId, $folder, $source, $search, $params->page ?? 1, min($params->per_page ?? 25, 100));

        $this->reportService->attachSummaries(collect($campaigns->items()), $canViewFinancials);

        return $campaigns;
    }

    /**
     * @return array<string, int>
     */
    public function folderCounts(int $organizerId): array
    {
        return $this->campaignRepository->countByFolder($organizerId);
    }

    /**
     * @return array{blocked_reason: string|null}
     */
    public function sendGate(int $accountId): array
    {
        try {
            $this->sendGate->assertAccountMaySend($accountId);
        } catch (CampaignNotSendableException $exception) {
            return ['blocked_reason' => $exception->getMessage()];
        }

        return ['blocked_reason' => null];
    }
}
