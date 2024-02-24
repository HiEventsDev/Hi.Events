<?php

namespace HiEvents\Service\Common\Auth;

use Carbon\Carbon;
use Illuminate\Config\Repository;
use HiEvents\DomainObjects\PasswordResetTokenDomainObject;
use HiEvents\Exceptions\InvalidPasswordResetTokenException;
use HiEvents\Repository\Interfaces\PasswordResetTokenRepositoryInterface;

class ResetPasswordTokenValidateService
{
    private PasswordResetTokenRepositoryInterface $passwordResetTokenRepository;
    private Repository $config;

    public function __construct(
        PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
        Repository                            $config
    )
    {
        $this->passwordResetTokenRepository = $passwordResetTokenRepository;
        $this->config = $config;
    }

    /**
     * @throws InvalidPasswordResetTokenException
     */
    public function validateAndFetchToken(string $token): PasswordResetTokenDomainObject
    {
        $resetToken = $this->passwordResetTokenRepository->findFirstWhere(['token' => $token]);
        if (!$resetToken) {
            throw new InvalidPasswordResetTokenException('Invalid token');
        }

        if ($this->isTokenExpired($resetToken->getCreatedAt())) {
            throw new InvalidPasswordResetTokenException('Expired token');
        }

        return $resetToken;
    }

    private function isTokenExpired(string $createdAt): bool
    {
        return (new Carbon($createdAt))
            ->addMinutes(
                $this->config->get('app.reset_password_token_expiry_in_min')
            )->isPast();
    }
}
