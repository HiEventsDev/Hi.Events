<?php

namespace HiEvents\Services\Domain\Auth\TwoFactor;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\TwoFactor\DTO\TwoFactorSetupDTO;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use PragmaRX\Google2FA\Google2FA;
use Psr\Log\LoggerInterface;

class TwoFactorAuthenticationService
{
    private const SECRET_LENGTH = 32;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly Encrypter $encrypter,
        private readonly UserRepositoryInterface $userRepository,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function isEnabled(UserDomainObject $user): bool
    {
        return $user->getTwoFactorConfirmedAt() !== null && $user->getTwoFactorSecret() !== null;
    }

    /**
     * @throws ResourceConflictException
     */
    public function beginSetup(UserDomainObject $user): TwoFactorSetupDTO
    {
        if ($this->isEnabled($user)) {
            throw new ResourceConflictException(__('Two-factor authentication is already enabled.'));
        }

        $secret = $this->google2fa->generateSecretKey(self::SECRET_LENGTH);

        $this->userRepository->updateWhere(
            attributes: [
                'two_factor_secret' => $this->encrypter->encryptString($secret),
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_last_used_timestep' => null,
            ],
            where: ['id' => $user->getId()],
        );

        return new TwoFactorSetupDTO(
            secret: $secret,
            otpauthUri: $this->google2fa->getQRCodeUrl(
                company: $this->config->get('app.name'),
                holder: $user->getEmail(),
                secret: $secret,
            ),
        );
    }

    public function confirmSetup(UserDomainObject $user): void
    {
        $this->userRepository->updateWhere(
            attributes: ['two_factor_confirmed_at' => now()],
            where: ['id' => $user->getId()],
        );
    }

    public function hasPendingSetup(UserDomainObject $user): bool
    {
        return $user->getTwoFactorSecret() !== null && $user->getTwoFactorConfirmedAt() === null;
    }

    public function verifyCode(UserDomainObject $user, string $code): bool
    {
        $secret = $this->decryptSecret($user);
        $code = preg_replace('/\D/', '', $code);

        if ($secret === null || strlen($code) !== $this->google2fa->getOneTimePasswordLength()) {
            return false;
        }

        $timestep = $this->google2fa->verifyKeyNewer(
            secret: $secret,
            key: $code,
            oldTimestamp: $user->getTwoFactorLastUsedTimestep() ?? 0,
        );

        if ($timestep === false) {
            return false;
        }

        $claimed = $this->userRepository->updateWhere(
            attributes: ['two_factor_last_used_timestep' => $timestep],
            where: [
                'id' => $user->getId(),
                static fn ($query) => $query
                    ->whereNull('two_factor_last_used_timestep')
                    ->orWhere('two_factor_last_used_timestep', '<', $timestep),
            ],
        );

        return $claimed === 1;
    }

    public function disable(int $userId): void
    {
        $this->userRepository->updateWhere(
            attributes: [
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_used_timestep' => null,
            ],
            where: ['id' => $userId],
        );
    }

    private function decryptSecret(UserDomainObject $user): ?string
    {
        if ($user->getTwoFactorSecret() === null) {
            return null;
        }

        try {
            return $this->encrypter->decryptString($user->getTwoFactorSecret());
        } catch (DecryptException) {
            $this->logger->error('Unable to decrypt two-factor secret', ['user_id' => $user->getId()]);

            return null;
        }
    }
}
