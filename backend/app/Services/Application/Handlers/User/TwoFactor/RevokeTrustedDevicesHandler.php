<?php

namespace HiEvents\Services\Application\Handlers\User\TwoFactor;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;

class RevokeTrustedDevicesHandler
{
    public function __construct(
        private readonly TrustedDeviceService $trustedDeviceService,
    ) {}

    public function revokeAll(int $userId): void
    {
        $this->trustedDeviceService->revokeAll($userId);
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function revoke(int $userId, int $deviceId): void
    {
        if (! $this->trustedDeviceService->revoke($userId, $deviceId)) {
            throw new ResourceNotFoundException(__('Trusted device not found'));
        }
    }
}
