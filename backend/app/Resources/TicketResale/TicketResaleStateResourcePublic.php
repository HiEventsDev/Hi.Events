<?php

namespace HiEvents\Resources\TicketResale;

use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleStateDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketResaleStateDTO
 */
class TicketResaleStateResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'can_list' => $this->can_list,
            /** @var 'LISTED'|'SOLD'|null */
            'status' => $this->status,
            'held_until' => $this->held_until,
            'withdrawal_requested' => $this->withdrawal_requested,
            'cutoff' => $this->cutoff,
        ];
    }
}
