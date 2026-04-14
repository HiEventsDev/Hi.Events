<?php

namespace HiEvents\Services\Domain\Email;

use HiEvents\DomainObjects\Enums\TransactionalEmailType;
use HiEvents\DomainObjects\Generated\OutgoingTransactionMessageDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OutgoingTransactionMessageStatus;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailable;
use Throwable;

class TransactionalEmailTrackingService
{
    public function __construct(
        private readonly OutgoingTransactionMessageRepositoryInterface $repository,
        private readonly EmailSuppressionService $suppressionService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function recordAndSend(
        Mailer                 $mailer,
        string                 $recipient,
        Mailable               $mail,
        TransactionalEmailType $emailType,
        string                 $subject,
        ?int                   $eventId = null,
        ?int                   $orderId = null,
        ?int                   $attendeeId = null,
        ?int                   $accountId = null,
        ?string                $locale = null,
        ?string                $retryForSesMessageId = null,
        ?int                   $retryForId = null,
    ): void
    {
        if ($this->suppressionService->isEmailSuppressed($recipient, $accountId, 'transactional')) {
            $this->recordMessage($recipient, $subject, $emailType, OutgoingTransactionMessageStatus::SUPPRESSED, $eventId, $orderId, $attendeeId, retryForId: $retryForId);
            return;
        }

        if ($retryForSesMessageId && method_exists($mail, '__set')) {
            $mail->retryForSesMessageId = $retryForSesMessageId;
        } elseif ($retryForSesMessageId && property_exists($mail, 'retryForSesMessageId')) {
            $mail->retryForSesMessageId = $retryForSesMessageId;
        }

        try {
            $pendingMail = $mailer->to($recipient);
            if ($locale) {
                $pendingMail->locale($locale);
            }
            $sentMessage = $pendingMail->sendNow($mail);
        } catch (Throwable $e) {
            $this->recordMessage($recipient, $subject, $emailType, OutgoingTransactionMessageStatus::FAILED, $eventId, $orderId, $attendeeId, retryForId: $retryForId);
            throw $e;
        }

        $this->recordMessage($recipient, $subject, $emailType, OutgoingTransactionMessageStatus::SENT, $eventId, $orderId, $attendeeId, $sentMessage?->getMessageId(), $retryForId);

        if ($retryForId !== null) {
            $this->repository->updateWhere(
                attributes: [
                    OutgoingTransactionMessageDomainObjectAbstract::RESOLVED_AT => now()->toDateTimeString(),
                    OutgoingTransactionMessageDomainObjectAbstract::RESOLUTION_TYPE => 'auto',
                ],
                where: [OutgoingTransactionMessageDomainObjectAbstract::ID => $retryForId],
            );
        }
    }

    private function recordMessage(
        string                              $recipient,
        string                              $subject,
        TransactionalEmailType              $emailType,
        OutgoingTransactionMessageStatus    $status,
        ?int                                $eventId,
        ?int                                $orderId,
        ?int                                $attendeeId,
        ?string                             $sesMessageId = null,
        ?int                                $retryForId = null,
    ): void
    {
        $attributes = [
            OutgoingTransactionMessageDomainObjectAbstract::EVENT_ID => $eventId,
            OutgoingTransactionMessageDomainObjectAbstract::ORDER_ID => $orderId,
            OutgoingTransactionMessageDomainObjectAbstract::ATTENDEE_ID => $attendeeId,
            OutgoingTransactionMessageDomainObjectAbstract::EMAIL_TYPE => $emailType->value,
            OutgoingTransactionMessageDomainObjectAbstract::RECIPIENT => strtolower($recipient),
            OutgoingTransactionMessageDomainObjectAbstract::SUBJECT => $subject,
            OutgoingTransactionMessageDomainObjectAbstract::STATUS => $status->value,
            OutgoingTransactionMessageDomainObjectAbstract::SES_MESSAGE_ID => $sesMessageId,
        ];

        if ($retryForId !== null) {
            $attributes[OutgoingTransactionMessageDomainObjectAbstract::RETRY_FOR_ID] = $retryForId;
        }

        $this->repository->create($attributes);
    }
}
