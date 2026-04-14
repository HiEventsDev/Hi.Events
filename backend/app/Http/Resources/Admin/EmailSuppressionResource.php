<?php

declare(strict_types=1);

namespace HiEvents\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailSuppressionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'reason' => $this->reason,
            'bounce_type' => $this->bounce_type,
            'bounce_sub_type' => $this->bounce_sub_type,
            'complaint_type' => $this->complaint_type,
            'source' => $this->source,
            'account_id' => $this->account_id,
            'account_name' => $this->account_name,
            'created_at' => $this->created_at,
        ];
    }
}
