<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends BaseModel
{
    use SoftDeletes;

    public function sent_by_user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'sent_by_user_id');
    }

    public function outgoing_messages(): HasMany
    {
        return $this->hasMany(OutgoingMessage::class);
    }

    protected function getCastMap(): array
    {
        return [
            'attendee_ids' => 'array',
            'product_ids' => 'array',
            'send_data' => 'array',
        ];
    }
}
