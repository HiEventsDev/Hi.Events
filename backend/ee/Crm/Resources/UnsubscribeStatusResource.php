<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\UnsubscribeStatusDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UnsubscribeStatusDTO
 */
class UnsubscribeStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'organizer_name' => $this->organizer_name,
            'email' => $this->email,
            'is_unsubscribed' => $this->is_unsubscribed,
            'can_resubscribe' => $this->can_resubscribe,
            /** @var 'EMAIL'|'SMS' */
            'channel' => $this->channel,
            'phone' => $this->phone,
        ];
    }
}
