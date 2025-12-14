<?php

namespace HiEvents\Services\Application\Handlers\Account\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

final class CreateAccountDTO extends BaseDTO
{
    public function __construct(
        public readonly string  $email,
        public readonly string  $password,
        public readonly string  $first_name,
        public readonly string $locale,
        public readonly ?string $last_name = null,
        public readonly ?string $timezone = null,
        public readonly ?string $currency_code = null,
        public readonly ?string $invite_token = null,
        public readonly bool $marketing_opt_in = false,
        public readonly ?string $utm_source = null,
        public readonly ?string $utm_medium = null,
        public readonly ?string $utm_campaign = null,
        public readonly ?string $utm_term = null,
        public readonly ?string $utm_content = null,
        public readonly ?string $referrer_url = null,
        public readonly ?string $landing_page = null,
        public readonly ?string $gclid = null,
        public readonly ?string $fbclid = null,
        public readonly ?array $utm_raw = null,
    )
    {
    }
}
