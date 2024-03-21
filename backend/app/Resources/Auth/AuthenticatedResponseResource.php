<?php

namespace HiEvents\Resources\Auth;

use HiEvents\Resources\Account\AccountResource;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Handlers\Auth\DTO\AuthenicatedResponseDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuthenicatedResponseDTO
 */
class AuthenticatedResponseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'token' => $this->token,
            'token_type' => 'bearer',
            'expires_in' => $this->expiresIn,
            'user' => new UserResource($this->user),
            'accounts' => AccountResource::collection($this->accounts),
        ];
    }
}
