<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Services\Domain\Message\MessageApprovalService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class ApproveMessageHandler
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly MessageApprovalService $approvalService,
    ) {}

    public function handle(int $messageId): MessageDomainObject
    {
        return $this->databaseManager->transaction(function () use ($messageId) {
            return $this->approveMessage($messageId);
        });
    }

    private function approveMessage(int $messageId): MessageDomainObject
    {
        $message = $this->messageRepository->findFirst($messageId);

        if ($message === null) {
            throw new ResourceNotFoundException(__('Message not found'));
        }

        if ($message->getStatus() !== MessageStatus::PENDING_REVIEW->name) {
            throw ValidationException::withMessages([
                'status' => [__('Message must be in pending review status to be approved')],
            ]);
        }

        $this->approvalService->releasePendingReview($message);

        return $this->messageRepository->findFirst($messageId);
    }
}
