<?php

namespace HiEvents\Resources\SelfService;

use HiEvents\Services\Domain\SelfService\DTO\SelfServiceSessionDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SelfServiceSessionDTO
 */
class SelfServiceSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'token' => $this->token,
            'expires_at' => $this->expires_at,
            'email' => $this->masked_email,
        ];
    }
}
