<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Admin;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\CampaignReviewExpiredException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignOwnerNotifier;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;

class ReviewCampaignHandler
{
    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CampaignLaunchService $launchService,
        private readonly CampaignOwnerNotifier $ownerNotifier,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function approve(int $campaignId, CarbonInterface $approvedVersion): void
    {
        try {
            $approved = $this->launchService->approve($this->find($campaignId), $approvedVersion);
        } catch (CampaignReviewExpiredException) {
            throw new ResourceConflictException(__('This campaign can no longer be sent: it waited too long for review, or its automation was switched off. It was withdrawn instead of approved.'));
        } catch (CampaignNotSendableException $exception) {
            throw new ResourceConflictException(__('This campaign can\'t be approved. Reject it so the organizer can fix it and send it again. The reason: :reason', ['reason' => $exception->getMessage()]));
        } catch (UnrecognizedProductIdException) {
            throw new ResourceConflictException(__('The early access tickets for this campaign were deleted, so it can\'t be approved. Reject it so the organizer can choose tickets again.'));
        }

        if ($approved) {
            return;
        }

        throw new ResourceConflictException(
            $this->find($campaignId)->getStatus() === CrmCampaignStatus::PENDING_REVIEW->value
                ? __('This campaign changed after you opened it. Reload the review queue and check it again.')
                : __('This campaign is no longer waiting for review.'),
        );
    }

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function reject(int $campaignId, ?string $note): void
    {
        $campaign = $this->find($campaignId);
        $plainNote = trim(strip_tags((string) $note));
        $plainNote = $plainNote === '' ? null : $plainNote;

        if (! $this->launchService->reject($campaign, $plainNote)) {
            throw new ResourceConflictException(__('This campaign is no longer waiting for review.'));
        }

        $this->ownerNotifier->notifyRejected($campaign, $plainNote);
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function find(int $campaignId): CrmCampaignDomainObject
    {
        $campaign = $this->campaignRepository->findFirstWhere(['id' => $campaignId]);

        if ($campaign === null) {
            throw new ResourceNotFoundException(__('Campaign not found'));
        }

        return $campaign;
    }
}
