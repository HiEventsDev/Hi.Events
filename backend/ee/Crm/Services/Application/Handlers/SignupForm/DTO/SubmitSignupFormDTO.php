<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SubmitSignupFormDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $public_id,
        public readonly string $email,
        public readonly ?string $first_name,
        public readonly ?string $last_name,
        public readonly ?string $honeypot,
        public readonly ?string $turnstile_token,
        public readonly ?string $ip_address,
        public readonly ?string $user_agent,
        public readonly string $locale,
    ) {}
}
