<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignMailFactory;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignReportService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\EarlyAccessService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\CampaignAiReviewService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\CampaignSmsPreviewService;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Support\Collection;

class GetCampaignsPendingReviewHandler
{
    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly CampaignMailFactory $mailFactory,
        private readonly CampaignSmsPreviewService $smsPreviewService,
        private readonly CampaignReportService $reportService,
        private readonly EarlyAccessService $earlyAccessService,
        private readonly CampaignAiReviewService $aiReviewService,
    ) {}

    public function handle(): Collection
    {
        $campaigns = $this->campaignRepository->findPendingReview();

        $accounts = $this->accountRepository->findWhereIn(
            'id',
            $campaigns->map(static fn (CrmCampaignDomainObject $campaign) => $campaign->getAccountId())->unique()->values()->all(),
        )->keyBy(static fn ($account) => $account->getId());

        $organizers = $this->organizerRepository->findWhereIn(
            'id',
            $campaigns->map(static fn (CrmCampaignDomainObject $campaign) => $campaign->getOrganizerId())->unique()->values()->all(),
        )->keyBy(static fn ($organizer) => $organizer->getId());

        $this->reportService->attachSummaries($campaigns, withRevenue: false);

        $isAutomaticCheckEnabled = (bool) $this->aiReviewService->isEnabled();
        $importOnly = $this->campaignRepository->countImportOnlyRecipients($campaigns->map(static fn (CrmCampaignDomainObject $campaign) => $campaign->getId())->all());

        return $campaigns->each(function (CrmCampaignDomainObject $campaign) use ($accounts, $organizers, $importOnly, $isAutomaticCheckEnabled): void {
            $campaign
                ->setAwaitingAutomaticCheck($isAutomaticCheckEnabled && $campaign->aiReviewVerdict() === null)
                ->setAccountName($accounts->get($campaign->getAccountId())?->getName())
                ->setAccountCreatedAt($accounts->get($campaign->getAccountId())?->getCreatedAt())
                ->setImportOnlyRecipientsCount($importOnly[$campaign->getId()] ?? 0)
                ->setOrganizerName($organizers->get($campaign->getOrganizerId())?->getName())
                ->setReplyTo($campaign->isSms() ? null : $organizers->get($campaign->getOrganizerId())?->getEmail())
                ->setEarlyAccessTicketTitles($campaign->offersEarlyAccess()
                    ? $this->earlyAccessService->findTickets((int) $campaign->getEventId(), $campaign->earlyAccessProductIds())
                        ->map(static fn (ProductDomainObject $product) => $product->getTitle())
                        ->values()
                        ->all()
                    : []);

            if ($campaign->isSms()) {
                $campaign->setPreviewText($this->smsPreviewService->preview($campaign, null)->variants[0]->text);
            } else {
                $campaign->setPreviewHtml($this->mailFactory->make($campaign, null)->renderAsSent());
            }
        });
    }
}
