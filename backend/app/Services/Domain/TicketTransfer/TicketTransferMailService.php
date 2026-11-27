<?php

namespace HiEvents\Services\Domain\TicketTransfer;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\TicketTransferNotice;
use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Helper\EmailMasker;
use HiEvents\Helper\Url;
use HiEvents\Mail\TicketTransfer\TicketTransferInvitationMail;
use HiEvents\Mail\TicketTransfer\TicketTransferNoticeMail;
use HiEvents\Services\Domain\TicketTransfer\DTO\TicketTransferContextDTO;
use Illuminate\Contracts\Mail\Mailer;

class TicketTransferMailService
{
    public function __construct(
        private readonly Mailer $mailer,
    ) {}

    public function sendInvitation(TicketTransferContextDTO $context, TicketTransferDomainObject $transfer, string $token): void
    {
        $this->mailer
            ->to($transfer->getRecipientEmail())
            ->locale($transfer->getLocale())
            ->send(new TicketTransferInvitationMail(
                event: $context->event,
                organizer: $context->event->getOrganizer(),
                eventSettings: $context->event->getEventSettings(),
                ticketTitle: $context->product->getTitle(),
                maskedSenderEmail: EmailMasker::mask($transfer->getSenderEmail()),
                claimUrl: sprintf(Url::getFrontEndUrlFromConfig(Url::TICKET_TRANSFER_CLAIM), $token),
                expiresAtFormatted: Carbon::parse($transfer->getExpiresAt(), 'UTC')
                    ->setTimezone($context->event->getTimezone())
                    ->isoFormat('dddd, MMMM D · h:mm A'),
            ));
    }

    public function sendNotice(
        TicketTransferNotice $notice,
        TicketTransferContextDTO $context,
        TicketTransferDomainObject $transfer,
        string $to,
        string $aboutEmail,
    ): void {
        $this->mailer
            ->to($to)
            ->locale($transfer->getLocale())
            ->send(new TicketTransferNoticeMail(
                notice: $notice,
                event: $context->event,
                organizer: $context->event->getOrganizer(),
                eventSettings: $context->event->getEventSettings(),
                ticketTitle: $context->product->getTitle(),
                maskedEmail: EmailMasker::mask($aboutEmail),
            ));
    }
}
