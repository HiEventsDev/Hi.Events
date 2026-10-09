<?php

namespace HiEvents\Resources\User\TwoFactor;

use HiEvents\DomainObjects\UserTrustedDeviceDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use Illuminate\Http\Request;

/**
 * @mixin UserTrustedDeviceDomainObject
 */
class TrustedDeviceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $cookie = $request->cookie(TrustedDeviceService::COOKIE_NAME);

        return [
            'id' => $this->getId(),
            'user_agent' => $this->getUserAgent(),
            'ip_address' => $this->getIpAddress(),
            'last_used_at' => $this->getLastUsedAt(),
            'expires_at' => $this->getExpiresAt(),
            'created_at' => $this->getCreatedAt(),
            'is_current_device' => is_string($cookie) && hash_equals($this->getTokenHash(), hash('sha256', $cookie)),
        ];
    }
}
