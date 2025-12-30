<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\Event\SendMessagesJob;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class ApproveMessageHandler
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly DatabaseManager            $databaseManager,
    )
    {
    }

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

        $updatedMessage = $this->messageRepository->updateFromArray($messageId, [
            'status' => MessageStatus::PROCESSING->name,
        ]);

        $sendData = $message->getSendData();
        $sendDataArray = is_string($sendData) ? json_decode($sendData, true) : $sendData;

        SendMessagesJob::dispatch(new SendMessageDTO(
            account_id: $sendDataArray['account_id'],
            event_id: $message->getEventId(),
            subject: $message->getSubject(),
            message: $message->getMessage(),
            type: MessageTypeEnum::fromName($message->getType()),
            is_test: false,
            send_copy_to_current_user: $sendDataArray['send_copy_to_current_user'] ?? false,
            sent_by_user_id: $message->getSentByUserId(),
            order_id: $message->getOrderId(),
            order_statuses: $sendDataArray['order_statuses'] ?? [],
            id: $message->getId(),
            attendee_ids: $message->getAttendeeIds() ?? [],
            product_ids: $message->getProductIds() ?? [],
        ));

        return $updatedMessage;
    }
}
