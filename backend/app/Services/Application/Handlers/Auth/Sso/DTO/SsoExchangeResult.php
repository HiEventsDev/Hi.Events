<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\SsoExchangeStatus;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;

class SsoExchangeResult extends BaseDataObject
{
    public function __construct(
        public readonly SsoExchangeStatus $status,
        public readonly string $providerName,
        public readonly ?LoginResponse $loginResponse = null,
        public readonly ?string $loginTicket = null,
        public readonly ?string $signupTicket = null,
        public readonly ?string $email = null,
        public readonly bool $emailVerified = false,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $reauthProof = null,
        public readonly ?string $returnPath = null,
        public readonly ?array $testResult = null,
    ) {}
}
