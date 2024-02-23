<?php

namespace TicketKitten\Service\Handler\Auth;

use TicketKitten\DomainObjects\PasswordResetTokenDomainObject;
use TicketKitten\Exceptions\InvalidPasswordResetTokenException;
use TicketKitten\Service\Common\Auth\ResetPasswordTokenValidateService;

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
