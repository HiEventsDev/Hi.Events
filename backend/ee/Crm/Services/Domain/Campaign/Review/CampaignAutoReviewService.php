<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Exceptions\CampaignReviewExpiredException;
use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPendingReviewJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\DTO\CampaignAiReviewResultDTO;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Support\Carbon;

class CampaignAutoReviewService
{
    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CampaignAiReviewService $aiReviewService,
        private readonly CampaignLaunchService $launchService,
    ) {}

    public function review(int $campaignId, int $recipientsCount, string $reviewReason): void
    {
        $campaign = $this->findPendingReview($campaignId);

        if ($campaign === null) {
            return;
        }

        try {
            $result = $this->aiReviewService->review($campaign, $recipientsCount);
        } catch (CampaignOrganizerDeletedException) {
            return;
        }

        if (! $this->campaignRepository->recordAiReview($campaignId, $result->toVerdictArray(), Carbon::parse($campaign->getUpdatedAt()))) {
            return;
        }

        if (! $result->approves) {
            NotifyCampaignPendingReviewJob::dispatch($campaignId, $recipientsCount, $reviewReason);

            return;
        }

        $this->approve($campaignId, $recipientsCount, $reviewReason, $result);
    }

    public function handOverUnreviewed(int $campaignId, int $recipientsCount, string $reviewReason): void
    {
        $campaign = $this->findPendingReview($campaignId);

        if ($campaign === null || ($campaign->aiReviewVerdict()['decision'] ?? null) === CampaignAiReviewResultDTO::DECISION_NEEDS_PERSON) {
            return;
        }

        $this->handToPerson(
            $campaign,
            $recipientsCount,
            $reviewReason,
            new CampaignAiReviewResultDTO(false, 0.0, []),
            [__('The automatic check couldn\'t be completed, so nothing was checked.')],
        );
    }

    private function approve(int $campaignId, int $recipientsCount, string $reviewReason, CampaignAiReviewResultDTO $result): void
    {
        $campaign = $this->findPendingReview($campaignId);

        if ($campaign === null) {
            return;
        }

        try {
            $this->launchService->approve($campaign, Carbon::parse($campaign->getUpdatedAt()));
        } catch (CampaignReviewExpiredException) {
            return;
        } catch (CampaignNotSendableException $exception) {
            $this->handToPerson($campaign, $recipientsCount, $reviewReason, $result, [$this->couldNotBeSent($exception->getMessage())]);
        } catch (UnrecognizedProductIdException) {
            $this->handToPerson($campaign, $recipientsCount, $reviewReason, $result, [$this->couldNotBeSent(__('The early access tickets for this campaign were deleted.'))]);
        }
    }

    private function couldNotBeSent(string $blocker): string
    {
        return __('The automatic check found no problems, but the campaign couldn\'t be sent: :reason', ['reason' => $blocker]);
    }

    /**
     * @param  string[]  $findings
     */
    private function handToPerson(CrmCampaignDomainObject $campaign, int $recipientsCount, string $reviewReason, CampaignAiReviewResultDTO $result, array $findings): void
    {
        $verdict = $result->needingPersonBecause($findings)->toVerdictArray();

        if ($this->campaignRepository->recordAiReview($campaign->getId(), $verdict, Carbon::parse($campaign->getUpdatedAt()))) {
            NotifyCampaignPendingReviewJob::dispatch($campaign->getId(), $recipientsCount, $reviewReason);
        }
    }

    private function findPendingReview(int $campaignId): ?CrmCampaignDomainObject
    {
        return $this->campaignRepository->findFirstWhere([
            'id' => $campaignId,
            'status' => CrmCampaignStatus::PENDING_REVIEW->value,
        ]);
    }
}
