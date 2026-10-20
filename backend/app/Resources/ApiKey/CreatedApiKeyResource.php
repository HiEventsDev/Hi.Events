<?php

namespace HiEvents\Resources\ApiKey;

use HiEvents\Services\Domain\Auth\ApiKey\DTO\CreatedApiKeyDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreatedApiKeyDTO
 */
class CreatedApiKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new ApiKeyResource($this->apiKey))->toArray($request),
            'key' => $this->plainTextKey,
        ];
    }
}
