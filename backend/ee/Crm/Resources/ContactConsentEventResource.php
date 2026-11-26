<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactConsentEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userName = trim(($this->user_first_name ?? '').' '.($this->user_last_name ?? ''));

        return [
            'id' => (int) $this->id,
            /** @var 'EMAIL'|'SMS' */
            'channel' => $this->channel,
            /** @var 'OPT_IN'|'OPT_OUT'|'BOUNCED'|'COMPLAINED'|'BOUNCE_CLEARED' */
            'action' => $this->action,
            /** @var 'ORDER'|'IMPORT'|'MANUAL'|'UNSUBSCRIBE_LINK'|'CAMPAIGN'|'CONTACT'|'FORM'|'SMS_REPLY' */
            'source' => $this->source_type,
            'email' => $this->email,
            'phone' => $this->phone,
            'occurred_at' => $this->occurred_at,
            'order_id' => $this->order_id === null ? null : (int) $this->order_id,
            'event_id' => $this->order_event_id === null ? null : (int) $this->order_event_id,
            'campaign_name' => $this->campaign_name,
            'import_file_name' => $this->import_file_name,
            'form_name' => $this->form_name,
            'user_name' => $userName === '' ? null : $userName,
        ];
    }
}
