<?php

namespace HiEvents\Services\Application\Handlers\Message;

use Carbon\Carbon;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Jobs\Event\SendMessagesJob;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HTMLPurifier;
use Illuminate\Support\Collection;

readonly class SendMessageHandler
{
    public function __construct(
        private OrderRepositoryInterface    $orderRepository,
        private AttendeeRepositoryInterface $attendeeRepository,
        private ProductRepositoryInterface  $productRepository,
        private MessageRepositoryInterface  $messageRepository,
        private AccountRepositoryInterface  $accountRepository,
        private HTMLPurifier                $purifier,
    )
    {
    }

    /**
     * @throws AccountNotVerifiedException
     */
    public function handle(SendMessageDTO $messageData): MessageDomainObject
    {
        $account = $this->accountRepository->findById($messageData->account_id);

        if ($account->getAccountVerifiedAt() === null) {
            throw new AccountNotVerifiedException(__('You cannot send messages until your account is verified.'));
        }

        $message = $this->messageRepository->create([
            'event_id' => $messageData->event_id,
            'subject' => $messageData->subject,
            'message' => $this->purifier->purify($messageData->message),
            'type' => $messageData->type->name,
            'order_id' => $this->getOrderId($messageData),
            'attendee_ids' => $this->getAttendeeIds($messageData)->toArray(),
            'product_ids' => $this->getProductIds($messageData)->toArray(),
            'sent_at' => Carbon::now()->toDateTimeString(),
            'sent_by_user_id' => $messageData->sent_by_user_id,
            'status' => MessageStatus::PROCESSING->name,
        ]);

        $updatedData = SendMessageDTO::fromArray([
            'id' => $message->getId(),
            'event_id' => $messageData->event_id,
            'subject' => $messageData->subject,
            'message' => $this->purifier->purify($messageData->message),
            'type' => $messageData->type,
            'is_test' => $messageData->is_test,
            'order_id' => $message->getOrderId(),
            'attendee_ids' => $message->getAttendeeIds(),
            'product_ids' => $message->getProductIds(),
            'send_copy_to_current_user' => $messageData->send_copy_to_current_user,
            'sent_by_user_id' => $messageData->sent_by_user_id,
            'account_id' => $messageData->account_id,
        ]);

        SendMessagesJob::dispatch($updatedData);

        return $message;
    }

    private function getAttendeeIds(SendMessageDTO $messageData): Collection
    {
        $attendees = $this->attendeeRepository->findWhereIn(
            field: 'id',
            values: $messageData->attendee_ids,
            additionalWhere: [
                'event_id' => $messageData->event_id,
                'status' => AttendeeStatus::ACTIVE->name,
            ],
            columns: ['id']
        );

        return $attendees->map(fn($attendee) => $attendee->getId());
    }


    private function getProductIds(SendMessageDTO $messageData): Collection
    {
        $products = $this->productRepository->findWhereIn(
            field: 'id',
            values: $messageData->product_ids,
            additionalWhere: [
                'event_id' => $messageData->event_id,
            ],
            columns: ['id']
        );

        return $products->map(fn($attendee) => $attendee->getId());
    }

    private function getOrderId(SendMessageDTO $messageData): ?int
    {
        return $this->orderRepository->findFirstWhere([
            'id' => $messageData->order_id,
            'event_id' => $messageData->event_id,
        ])?->getId();
    }
}
