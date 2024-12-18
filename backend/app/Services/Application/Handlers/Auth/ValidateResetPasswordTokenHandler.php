<?php

namespace HiEvents\Services\Application\Handlers\Auth;

use HiEvents\DomainObjects\PasswordResetTokenDomainObject;
use HiEvents\Exceptions\InvalidPasswordResetTokenException;
use HiEvents\Services\Domain\Auth\ResetPasswordTokenValidateService;

class ValidateResetPasswordTokenHandler
{
    private ResetPasswordTokenValidateService $passwordTokenValidateService;

    public function __construct(ResetPasswordTokenValidateService $passwordTokenValidateService)
    {
        $this->passwordTokenValidateService = $passwordTokenValidateService;
    }

    /**
     * @throws InvalidPasswordResetTokenException
     */
    public function handle(string $token): PasswordResetTokenDomainObject
    {
        return $this->passwordTokenValidateService->validateAndFetchToken($token);
    }
}
