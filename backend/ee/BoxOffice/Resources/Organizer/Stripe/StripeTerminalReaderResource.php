<?php

namespace HiEvents\Enterprise\BoxOffice\Resources\Organizer\Stripe;

use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalReaderDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TerminalReaderDTO
 */
class StripeTerminalReaderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'device_type' => $this->device_type,
            /** @var 'online'|'offline'|'unknown'|'unavailable' */
            'status' => $this->status,
            'is_available' => $this->is_available,
        ];
    }
}
