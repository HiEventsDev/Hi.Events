<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CompleteSsoSignupDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $signupTicket,
        public readonly string $binding,
        public readonly string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $timezone,
        public readonly ?string $currencyCode,
        public readonly ?string $locale,
        public readonly bool $marketingOptIn,
        public readonly ?string $emailCode = null,
        public readonly ?string $inviteToken = null,
        public readonly array $attribution = [],
    ) {}
}
