<?php

namespace HiEvents\Services\Domain\Email\Ses\EventHandlers;

use HiEvents\DomainObjects\Status\EmailSuppressionReasonEnum;
use HiEvents\DomainObjects\Status\EmailSuppressionSourceEnum;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Services\Domain\Email\EmailSuppressionService;
use Illuminate\Log\Logger;

class BounceHandler
{
    public function __construct(
        private readonly EmailSuppressionService              $emailSuppressionService,
        private readonly OutgoingMessageRepositoryInterface   $outgoingMessageRepository,
        private readonly Logger                               $logger,
    )
    {
    }

    public function handle(array $message, array $snsPayload): void
    {
        $bounce = $message['bounce'] ?? [];
        $bounceType = $bounce['bounceType'] ?? 'Undetermined';
        $bounceSubType = $bounce['bounceSubType'] ?? null;
        $recipients = $bounce['bouncedRecipients'] ?? [];
        $snsMessageId = $snsPayload['MessageId'] ?? null;
        $sesMessageId = $message['mail']['messageId'] ?? null;

        foreach ($recipients as $recipient) {
            $email = strtolower($recipient['emailAddress'] ?? '');

            if (empty($email)) {
                continue;
            }

            $accountId = $this->outgoingMessageRepository->findAccountIdByRecipientEmail($email);

            $this->logger->info('Processing SES bounce', [
                'email' => $email,
                'bounce_type' => $bounceType,
                'bounce_sub_type' => $bounceSubType,
                'account_id' => $accountId,
                'ses_message_id' => $sesMessageId,
            ]);

            $this->emailSuppressionService->suppressEmail(
                email: $email,
                reason: EmailSuppressionReasonEnum::BOUNCE->value,
                source: EmailSuppressionSourceEnum::SES_NOTIFICATION->value,
                accountId: $accountId,
                bounceType: $bounceType,
                bounceSubType: $bounceSubType,
                snsMessageId: $snsMessageId,
                rawPayload: $snsPayload,
            );

            if ($sesMessageId && $this->outgoingMessageRepository->markAsBounced($sesMessageId)) {
                $this->logger->info('Marked outgoing message as bounced', ['ses_message_id' => $sesMessageId]);
            }
        }
    }
}
