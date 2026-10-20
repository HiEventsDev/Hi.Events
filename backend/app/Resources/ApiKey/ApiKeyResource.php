<?php

namespace HiEvents\Resources\ApiKey;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin PersonalAccessTokenDomainObject
 */
class ApiKeyResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->getUser();

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'token_hint' => $this->getTokenHint(),
            /** @var 'READ_ONLY'|'READ_WRITE' */
            'access' => $this->getAccess()->value,
            'last_used_at' => $this->getLastUsedAt(),
            'expires_at' => $this->getExpiresAt(),
            'created_at' => $this->getCreatedAt(),
            'is_expired' => $this->getExpiresAt() !== null && CarbonImmutable::parse($this->getExpiresAt())->isPast(),
            'is_own' => $this->getTokenableId() === $request->user()?->getAuthIdentifier(),
            /** @var array{id: int, full_name: string, email: string}|null */
            'owner' => $owner === null ? null : [
                'id' => $owner->getId(),
                'full_name' => $owner->getFullName(),
                'email' => $owner->getEmail(),
            ],
        ];
    }
}
