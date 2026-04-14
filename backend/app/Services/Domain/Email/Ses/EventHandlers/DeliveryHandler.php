<?php

namespace HiEvents\Services\Domain\Email\Ses\EventHandlers;

use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use Illuminate\Log\Logger;

class DeliveryHandler
{
    public function __construct(
        private readonly OutgoingMessageRepositoryInterface               $outgoingMessageRepository,
        private readonly OutgoingTransactionMessageRepositoryInterface    $outgoingTransactionMessageRepository,
        private readonly Logger                                           $logger,
    )
    {
    }

    public function handle(array $message, array $snsPayload): void
    {
        $sesMessageId = $message['mail']['messageId'] ?? null;

        if (!$sesMessageId) {
            $this->logger->debug('Delivery notification missing mail.messageId');
            return;
        }

        $recipients = $message['delivery']['recipients'] ?? [];

        $this->logger->info('Processing SES delivery', [
            'recipients' => $recipients,
            'ses_message_id' => $sesMessageId,
        ]);

        $this->markOutgoingMessagesAsDelivered($sesMessageId);
    }

    private function markOutgoingMessagesAsDelivered(string $sesMessageId): void
    {
        if ($this->outgoingMessageRepository->markAsDelivered($sesMessageId)) {
            $this->logger->info('Marked outgoing message as delivered', ['ses_message_id' => $sesMessageId]);
        }

        $transactionMessage = $this->outgoingTransactionMessageRepository->findBySesMessageId($sesMessageId);

        if ($transactionMessage) {
            $this->outgoingTransactionMessageRepository->markAsDelivered($transactionMessage->getId());
            $this->logger->info('Marked transaction message as delivered', [
                'ses_message_id' => $sesMessageId,
                'transaction_message_id' => $transactionMessage->getId(),
            ]);
        }
    }
}
