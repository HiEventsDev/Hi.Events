<?php

namespace HiEvents\Enterprise\BoxOffice\Resources\Organizer\Stripe;

use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalReadersResponseDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TerminalReadersResponseDTO
 */
class StripeTerminalReadersResponseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'readers' => StripeTerminalReaderResource::collection($this->readers),
            'stripe_configured' => $this->stripe_configured,
            'stripe_connected' => $this->stripe_connected,
        ];
    }
}
