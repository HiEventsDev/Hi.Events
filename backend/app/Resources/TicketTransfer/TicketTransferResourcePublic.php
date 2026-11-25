<?php

namespace HiEvents\Resources\TicketTransfer;

use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Helper\EmailMasker;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketTransferDomainObject
 */
class TicketTransferResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'recipient_email' => EmailMasker::mask($this->getRecipientEmail()),
            'expires_at' => $this->getExpiresAt(),
        ];
    }
}
