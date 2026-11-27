<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\Enterprise\Crm\Services\Domain\Identity\PhoneMask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignRecipientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'contact_id' => $this->contact_id === null ? null : (int) $this->contact_id,
            'email' => $this->contact_id === null ? null : $this->email,
            'phone' => $this->contact_id === null || $this->phone === null ? null : PhoneMask::mask($this->phone),
            'full_name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            /** @var 'PENDING'|'SENDING'|'SENT'|'FAILED'|'SKIPPED' */
            'status' => $this->status,
            /** @var 'PROVIDER_SUPPRESSED'|'PROVIDER_UNAVAILABLE'|'PROVIDER_REJECTED'|'TIMEOUT'|'UNDELIVERED'|'SEND_ERROR'|'UNSUBSCRIBED'|'BOUNCED'|'NO_CONSENT'|'EMAIL_CHANGED'|'NO_PHONE'|'COUNTRY_NOT_ENABLED'|'CONTACT_DELETED'|'ALREADY_BOUGHT'|'CAMPAIGN_STOPPED'|null */
            'failure_reason' => $this->failure_reason,
            'sent_at' => $this->sent_at,
            'delivered_at' => $this->delivered_at,
            'opened_at' => $this->first_opened_at,
            'clicked_at' => $this->first_clicked_at,
            'bounced_at' => $this->bounced_at,
            'complained_at' => $this->complained_at,
        ];
    }
}
