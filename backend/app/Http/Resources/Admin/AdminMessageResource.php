<?php

declare(strict_types=1);

namespace HiEvents\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_title' => $this->event_title,
            'account_name' => $this->account_name,
            'subject' => $this->subject,
            'message' => $this->message,
            'type' => $this->type,
            'status' => $this->status,
            'recipients_count' => (int)$this->recipients_count,
            'sent_by' => trim(($this->sent_by_first_name ?? '') . ' ' . ($this->sent_by_last_name ?? '')),
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
            'eligibility_failures' => $this->eligibility_failures,
        ];
    }
}
