<?php

namespace HiEvents\Resources\DeliveryIssue;

use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

class DeliveryIssueResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'email_type' => $this->email_type,
            'status' => $this->status,
            'subject' => $this->subject,
            'recipient' => $this->recipient,
            'updated_at' => $this->updated_at,
            'resolved_at' => $this->resolved_at,
            'retry_for_id' => $this->retry_for_id ?? null,
        ];
    }
}
