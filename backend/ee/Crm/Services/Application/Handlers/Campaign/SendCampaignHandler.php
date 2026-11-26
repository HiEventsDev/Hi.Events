<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignAudienceService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignReportService;
use HiEvents\Exceptions\PermissionRequiredException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Support\Carbon;

class SendCampaignHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly CampaignLookupService $lookupService,
        private readonly CampaignLaunchService $launchService,
        private readonly CampaignAudienceService $audienceService,
        private readonly CampaignReportService $reportService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws CampaignNotSendableException
     * @throws PermissionRequiredException
     * @throws UnrecognizedProductIdException
     */
    public function handle(int $organizerId, int $campaignId, ?string $scheduledAt, bool $canViewFinancials): CrmCampaignDomainObject
    {
        $campaign = $this->lookupService->findForOrganizer($organizerId, $campaignId);

        if (! $campaign->isEditable()) {
            throw new CampaignNotSendableException(__('This campaign has already been sent or is being sent.'));
        }

        if (! $canViewFinancials && $this->audienceService->needsFinancialAccess($campaign)) {
            throw new PermissionRequiredException(__('This campaign filters by spend, so only members who can view financials can send it.'));
        }

        if ($scheduledAt === null) {
            $this->launchService->launch($campaign);
        } else {
            $scheduledAtUtc = DateHelper::convertToUTC(
                $scheduledAt,
                $this->organizerRepository->findById($organizerId)->getTimezone() ?? 'UTC',
            );

            if (Carbon::parse($scheduledAtUtc)->isPast()) {
                throw new CampaignNotSendableException(__('The scheduled time must be in the future.'));
            }

            $this->launchService->schedule($campaign, Carbon::parse($scheduledAtUtc));
        }

        $updated = $this->lookupService->findForOrganizer($organizerId, $campaignId);
        $this->reportService->attachDetail($updated, $canViewFinancials);

        return $updated;
    }
}
