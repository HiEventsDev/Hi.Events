<?php

namespace HiEvents\Services\Application\Handlers\User\TwoFactor;

use HiEvents\DomainObjects\UserTrustedDeviceDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserTrustedDeviceRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\TwoFactor\DTO\TwoFactorStatusDTO;
use HiEvents\Services\Domain\Auth\TwoFactor\RecoveryCodeService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorRequirementService;

class GetTwoFactorStatusHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserTrustedDeviceRepositoryInterface $trustedDeviceRepository,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly RecoveryCodeService $recoveryCodeService,
        private readonly TwoFactorRequirementService $twoFactorRequirementService,
    ) {}

    public function handle(int $userId): TwoFactorStatusDTO
    {
        $user = $this->userRepository->findById($userId);
        $enabled = $this->twoFactorAuthenticationService->isEnabled($user);

        return new TwoFactorStatusDTO(
            enabled: $enabled,
            confirmedAt: $enabled ? $user->getTwoFactorConfirmedAt() : null,
            recoveryCodesRemaining: $enabled ? $this->recoveryCodeService->remaining($user) : 0,
            trustedDevices: $enabled
                ? $this->trustedDeviceRepository->findWhere(
                    where: [
                        'user_id' => $userId,
                        ['expires_at', '>', now()],
                    ],
                    orderAndDirections: [
                        new OrderAndDirection(UserTrustedDeviceDomainObject::LAST_USED_AT, OrderAndDirection::DIRECTION_DESC),
                    ],
                )
                : collect(),
            requiredByAccounts: $this->twoFactorRequirementService->namesOfAccountsRequiringTwoFactor($userId),
        );
    }
}
