<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Message;

use Carbon\Carbon;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;

class MessageApprovalService
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly MessageDispatchService $messageDispatchService,
    ) {}

    public function releasePendingReview(MessageDomainObject $message): void
    {
        $scheduledAt = $message->getScheduledAt();

        if ($scheduledAt !== null && Carbon::parse($scheduledAt)->isFuture()) {
            $this->messageRepository->updateWhere(
                ['status' => MessageStatus::SCHEDULED->name],
                ['id' => $message->getId(), 'status' => MessageStatus::PENDING_REVIEW->name],
            );

            return;
        }

        $this->messageDispatchService->dispatchMessage($message, MessageStatus::PENDING_REVIEW);
    }
}
