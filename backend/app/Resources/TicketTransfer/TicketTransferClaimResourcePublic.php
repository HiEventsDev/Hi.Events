<?php

namespace HiEvents\Resources\TicketTransfer;

use HiEvents\DomainObjects\Status\TicketTransferStatus;
use HiEvents\Helper\EmailMasker;
use HiEvents\Resources\Question\QuestionResourcePublic;
use HiEvents\Services\Application\Handlers\TicketTransfer\DTO\TicketTransferClaimDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketTransferClaimDTO
 */
class TicketTransferClaimResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        return [
            /** @var 'PENDING'|'ACCEPTED'|'CANCELLED'|'DECLINED'|'EXPIRED' */
            'status' => $this->transfer->hasExpired() ? TicketTransferStatus::EXPIRED->name : $this->transfer->getStatus(),
            'can_accept' => $this->can_accept,
            'recipient_email' => $this->transfer->getRecipientEmail(),
            'sender_email' => EmailMasker::mask($this->transfer->getSenderEmail()),
            'expires_at' => $this->transfer->getExpiresAt(),
            'event_id' => $this->context->event->getId(),
            'event_title' => $this->context->event->getTitle(),
            'event_timezone' => $this->context->event->getTimezone(),
            'product_id' => $this->context->product->getId(),
            'ticket_title' => $this->context->product->getTitle(),
            'start_date' => $this->context->occurrence->getStartDate(),
            'questions' => QuestionResourcePublic::collection($this->questions),
        ];
    }
}
