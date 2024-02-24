<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;

class Message extends BaseModel
{
    public function sent_by_user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'sent_by_user_id');
    }

    protected function getCastMap(): array
    {
        return [
            'attendee_ids' => 'array',
            'ticket_ids' => 'array',
            'send_data' => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }
}
