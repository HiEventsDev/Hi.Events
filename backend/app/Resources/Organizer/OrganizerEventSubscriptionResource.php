<?php

namespace HiEvents\Resources\Organizer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizerEventSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'email' => $this->email,
            'status' => $this->unsubscribed_at ? 'unsubscribed' : ($this->confirmed_at ? 'confirmed' : 'pending'),
            /** @var 'public'|'checkout' */
            'source' => $this->source,
            'subscribed_at' => $this->subscribed_at,
            /** @var string|null */
            'confirmed_at' => $this->confirmed_at,
            /** @var string|null */
            'unsubscribed_at' => $this->unsubscribed_at,
        ];
    }
}
