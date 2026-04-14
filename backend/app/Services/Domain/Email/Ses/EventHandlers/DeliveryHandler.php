<?php

namespace HiEvents\Services\Domain\Email\Ses\EventHandlers;

use HiEvents\DomainObjects\Generated\OutgoingMessageDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OutgoingTransactionMessageDomainObjectAbstract;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use Illuminate\Log\Logger;

class DeliveryHandler
{
    use ExtractsRetryHeader;

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

        $retryForSesMessageId = $this->getRetryForSesMessageId($message);
        if ($retryForSesMessageId) {
            $this->autoResolveOriginal($retryForSesMessageId);
        }
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

    private function autoResolveOriginal(string $originalSesMessageId): void
    {
        $resolvedAt = now()->toDateTimeString();

        $transactionMessage = $this->outgoingTransactionMessageRepository->findFirstWhere([
            OutgoingTransactionMessageDomainObjectAbstract::SES_MESSAGE_ID => $originalSesMessageId,
        ]);

        if ($transactionMessage && !$transactionMessage->getResolvedAt()) {
            $this->outgoingTransactionMessageRepository->updateWhere(
                attributes: [
                    OutgoingTransactionMessageDomainObjectAbstract::RESOLVED_AT => $resolvedAt,
                    OutgoingTransactionMessageDomainObjectAbstract::RESOLUTION_TYPE => 'auto',
                ],
                where: [OutgoingTransactionMessageDomainObjectAbstract::ID => $transactionMessage->getId()],
            );
            $this->logger->info('Auto-resolved original transaction message', [
                'original_ses_message_id' => $originalSesMessageId,
                'original_id' => $transactionMessage->getId(),
            ]);
            return;
        }

        $outgoingMessage = $this->outgoingMessageRepository->findFirstWhere([
            OutgoingMessageDomainObjectAbstract::SES_MESSAGE_ID => $originalSesMessageId,
        ]);

        if ($outgoingMessage && !$outgoingMessage->getResolvedAt()) {
            $this->outgoingMessageRepository->updateWhere(
                attributes: [
                    OutgoingMessageDomainObjectAbstract::RESOLVED_AT => $resolvedAt,
                    OutgoingMessageDomainObjectAbstract::RESOLUTION_TYPE => 'auto',
                ],
                where: [OutgoingMessageDomainObjectAbstract::ID => $outgoingMessage->getId()],
            );
            $this->logger->info('Auto-resolved original outgoing message', [
                'original_ses_message_id' => $originalSesMessageId,
                'original_id' => $outgoingMessage->getId(),
            ]);
        }
    }
}
