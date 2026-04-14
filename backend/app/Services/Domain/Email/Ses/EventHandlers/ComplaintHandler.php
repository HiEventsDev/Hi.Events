<?php

namespace HiEvents\Services\Domain\Email\Ses\EventHandlers;

use HiEvents\DomainObjects\Status\EmailSuppressionReasonEnum;
use HiEvents\DomainObjects\Status\EmailSuppressionSourceEnum;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use HiEvents\Services\Domain\Email\EmailSuppressionService;
use Illuminate\Log\Logger;

class ComplaintHandler
{
    public function __construct(
        private readonly EmailSuppressionService                          $emailSuppressionService,
        private readonly OutgoingMessageRepositoryInterface               $outgoingMessageRepository,
        private readonly OutgoingTransactionMessageRepositoryInterface    $outgoingTransactionMessageRepository,
        private readonly Logger                                           $logger,
    )
    {
    }

    public function handle(array $message, array $snsPayload): void
    {
        $complaint = $message['complaint'] ?? [];
        $complaintType = $complaint['complaintFeedbackType'] ?? null;
        $recipients = $complaint['complainedRecipients'] ?? [];
        $snsMessageId = $snsPayload['MessageId'] ?? null;
        $sesMessageId = $message['mail']['messageId'] ?? null;

        foreach ($recipients as $recipient) {
            $email = strtolower($recipient['emailAddress'] ?? '');

            if (empty($email)) {
                continue;
            }

            $accountId = $this->outgoingMessageRepository->findAccountIdByRecipientEmail($email)
                ?? $this->outgoingTransactionMessageRepository->findAccountIdByRecipientEmail($email);

            $this->logger->info('Processing SES complaint', [
                'email' => $email,
                'complaint_type' => $complaintType,
                'account_id' => $accountId,
                'ses_message_id' => $sesMessageId,
            ]);

            $this->emailSuppressionService->suppressEmail(
                email: $email,
                reason: EmailSuppressionReasonEnum::COMPLAINT->value,
                source: EmailSuppressionSourceEnum::SES_NOTIFICATION->value,
                accountId: $accountId,
                complaintType: $complaintType,
                snsMessageId: $snsMessageId,
                rawPayload: $snsPayload,
            );

            if ($sesMessageId) {
                $this->markOutgoingMessagesAsBounced($sesMessageId);
            }
        }
    }

    private function markOutgoingMessagesAsBounced(string $sesMessageId): void
    {
        if ($this->outgoingMessageRepository->markAsBounced($sesMessageId)) {
            $this->logger->info('Marked outgoing message as bounced', ['ses_message_id' => $sesMessageId]);
        }

        $transactionMessage = $this->outgoingTransactionMessageRepository->findBySesMessageId($sesMessageId);

        if ($transactionMessage) {
            $this->outgoingTransactionMessageRepository->markAsBounced($transactionMessage->getId());
            $this->logger->info('Marked transaction message as bounced', [
                'ses_message_id' => $sesMessageId,
                'transaction_message_id' => $transactionMessage->getId(),
            ]);
        }
    }
}
