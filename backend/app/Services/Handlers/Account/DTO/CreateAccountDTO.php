<?php

namespace HiEvents\Services\Handlers\Account\DTO;

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
    )
    {
    }
}
