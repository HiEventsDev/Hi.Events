<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Message;

use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Jobs\Event\SendMessagesJob;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use Illuminate\Support\Facades\Log;
use Throwable;

class MessageDispatchService
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
    )
    {
    }

    public function dispatchMessage(MessageDomainObject $message, MessageStatus $expectedStatus = MessageStatus::SCHEDULED): void
    {
        $sendData = $message->getSendData();
        $sendDataArray = is_string($sendData) ? json_decode($sendData, true) : $sendData;

        if (!is_array($sendDataArray) || !isset($sendDataArray['account_id'])) {
            Log::error('Message has invalid send_data, marking as FAILED', [
                'message_id' => $message->getId(),
            ]);
            $this->messageRepository->updateFromArray($message->getId(), [
                'status' => MessageStatus::FAILED->name,
            ]);
            return;
        }

        $updated = $this->messageRepository->updateWhere(
            ['status' => MessageStatus::PROCESSING->name],
            ['id' => $message->getId(), 'status' => $expectedStatus->name],
        );

        if ($updated === 0) {
            Log::info('Message status changed before dispatch, skipping', [
                'message_id' => $message->getId(),
            ]);
            return;
        }

        try {
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
        } catch (Throwable $e) {
            Log::error('Failed to dispatch SendMessagesJob, reverting status', [
                'message_id' => $message->getId(),
                'error' => $e->getMessage(),
            ]);
            $this->messageRepository->updateWhere(
                ['status' => $expectedStatus->name],
                ['id' => $message->getId(), 'status' => MessageStatus::PROCESSING->name],
            );
            throw $e;
        }
    }
}
