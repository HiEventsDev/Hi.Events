<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Message\Review;

use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Jobs\Message\MessagePendingReviewJob;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Services\Domain\Message\MessageApprovalService;
use HiEvents\Services\Domain\Message\Review\DTO\MessageAiReviewResultDTO;

class MessageAutoReviewService
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly MessageAiReviewService $aiReviewService,
        private readonly MessageApprovalService $approvalService,
    ) {}

    public function review(int $messageId, int $recipientsCount): void
    {
        $message = $this->findPendingReview($messageId);

        if ($message === null) {
            return;
        }

        $decision = $message->aiReviewVerdict()['decision'] ?? null;

        if ($decision === null) {
            $result = $this->aiReviewService->review($message, $recipientsCount);

            if (! $this->recordVerdict($messageId, $result)) {
                return;
            }

            $decision = $result->toVerdictArray()['decision'];
        }

        if ($decision !== MessageAiReviewResultDTO::DECISION_APPROVED) {
            MessagePendingReviewJob::dispatch($messageId, $message->eligibilityFailureValues());

            return;
        }

        $this->approvalService->releasePendingReview($message);
    }

    public function handOverUnreviewed(int $messageId): void
    {
        $message = $this->findPendingReview($messageId);

        if ($message === null || ($message->aiReviewVerdict()['decision'] ?? null) === MessageAiReviewResultDTO::DECISION_NEEDS_PERSON) {
            return;
        }

        $unreviewed = new MessageAiReviewResultDTO(false, 0.0, [
            __('The automatic check couldn\'t be completed, so nothing was checked.'),
        ]);

        if ($this->recordVerdict($messageId, $unreviewed)) {
            MessagePendingReviewJob::dispatch($messageId, $message->eligibilityFailureValues());
        }
    }

    private function recordVerdict(int $messageId, MessageAiReviewResultDTO $result): bool
    {
        return $this->messageRepository->updateWhere(
            [MessageDomainObject::AI_REVIEW => json_encode($result->toVerdictArray())],
            ['id' => $messageId, 'status' => MessageStatus::PENDING_REVIEW->name],
        ) === 1;
    }

    private function findPendingReview(int $messageId): ?MessageDomainObject
    {
        return $this->messageRepository->findFirstWhere([
            'id' => $messageId,
            'status' => MessageStatus::PENDING_REVIEW->name,
        ]);
    }
}
