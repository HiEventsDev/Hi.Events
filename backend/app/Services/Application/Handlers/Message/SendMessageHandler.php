<?php

namespace HiEvents\Services\Application\Handlers\Message;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Exceptions\MessagingTierLimitExceededException;
use HiEvents\Jobs\Event\SendMessagesJob;
use HiEvents\Jobs\Message\MessagePendingReviewJob;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Message\MessagingEligibilityService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Config\Repository;
use Illuminate\Support\Collection;

class SendMessageHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface      $orderRepository,
        private readonly AttendeeRepositoryInterface   $attendeeRepository,
        private readonly ProductRepositoryInterface    $productRepository,
        private readonly MessageRepositoryInterface    $messageRepository,
        private readonly AccountRepositoryInterface    $accountRepository,
        private readonly HtmlPurifierService           $purifier,
        private readonly Repository                    $config,
        private readonly MessagingEligibilityService   $eligibilityService,
    )
    {
    }

    /**
     * @throws AccountNotVerifiedException
     * @throws MessagingTierLimitExceededException
     */
    public function handle(SendMessageDTO $messageData): MessageDomainObject
    {
        $account = $this->accountRepository->findById($messageData->account_id);

        if ($account->getAccountVerifiedAt() === null) {
            throw new AccountNotVerifiedException(__('You cannot send messages until your account is verified.'));
        }

        if ($this->config->get('app.saas_mode_enabled') && !$account->getIsManuallyVerified()) {
            throw new AccountNotVerifiedException(
                __('Due to issues with spam, you must contact us to enable your account for sending messages. ' .
                    'Please contact us at :email', [
                    'email' => $this->config->get('app.platform_support_email'),
                ])
            );
        }

        $recipientCount = $this->estimateRecipientCount($messageData);
        $tierViolation = $this->eligibilityService->checkTierLimits(
            $messageData->account_id,
            $recipientCount,
            $messageData->message
        );

        if ($tierViolation !== null) {
            throw new MessagingTierLimitExceededException($tierViolation);
        }

        $eligibilityFailure = $this->eligibilityService->checkEligibility(
            $messageData->account_id,
            $messageData->event_id
        );

        $status = $eligibilityFailure !== null
            ? MessageStatus::PENDING_REVIEW
            : MessageStatus::PROCESSING;

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
            'status' => $status->name,
            'eligibility_failures' => $eligibilityFailure?->getFailureValues(),
            'send_data' => [
                'is_test' => $messageData->is_test,
                'send_copy_to_current_user' => $messageData->send_copy_to_current_user,
                'order_statuses' => $messageData->order_statuses,
                'account_id' => $messageData->account_id,
                'attendee_ids' => $messageData->attendee_ids,
                'product_ids' => $messageData->product_ids,
            ],
        ]);

        if ($status === MessageStatus::PENDING_REVIEW) {
            MessagePendingReviewJob::dispatch($message->getId(), $eligibilityFailure->getFailureValues());
        } else {
            $updatedData = SendMessageDTO::fromArray([
                'account_id' => $messageData->account_id,
                'event_id' => $messageData->event_id,
                'subject' => $messageData->subject,
                'message' => $this->purifier->purify($messageData->message),
                'type' => $messageData->type,
                'is_test' => $messageData->is_test,
                'send_copy_to_current_user' => $messageData->send_copy_to_current_user,
                'sent_by_user_id' => $messageData->sent_by_user_id,
                'order_id' => $message->getOrderId(),
                'order_statuses' => $messageData->order_statuses,
                'id' => $message->getId(),
                'attendee_ids' => $message->getAttendeeIds(),
                'product_ids' => $message->getProductIds(),
            ]);

            SendMessagesJob::dispatch($updatedData);
        }

        return $message;
    }

    private function estimateRecipientCount(SendMessageDTO $messageData): int
    {
        return match ($messageData->type) {
            MessageTypeEnum::INDIVIDUAL_ATTENDEES => count($messageData->attendee_ids ?? []),
            MessageTypeEnum::ORDER_OWNER => 1,
            MessageTypeEnum::ALL_ATTENDEES => $this->attendeeRepository->countWhere([
                'event_id' => $messageData->event_id,
            ]),
            MessageTypeEnum::TICKET_HOLDERS => $this->attendeeRepository->countWhere([
                'event_id' => $messageData->event_id,
                ['product_id', 'in', $messageData->product_ids ?? []],
            ]),
            MessageTypeEnum::ORDER_OWNERS_WITH_PRODUCT => $this->orderRepository->countOrdersAssociatedWithProducts(
                eventId: $messageData->event_id,
                productIds: $messageData->product_ids ?? [],
                orderStatuses: $messageData->order_statuses ?? ['COMPLETED'],
            ),
        };
    }

    private function getAttendeeIds(SendMessageDTO $messageData): Collection
    {
        $attendees = $this->attendeeRepository->findWhereIn(
            field: 'id',
            values: $messageData->attendee_ids,
            additionalWhere: [
                'event_id' => $messageData->event_id,
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

        return $products->map(fn($product) => $product->getId());
    }

    private function getOrderId(SendMessageDTO $messageData): ?int
    {
        return $this->orderRepository->findFirstWhere([
            'id' => $messageData->order_id,
            'event_id' => $messageData->event_id,
        ])?->getId();
    }
}
