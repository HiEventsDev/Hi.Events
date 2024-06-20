<?php

namespace HiEvents\Services\Handlers\User\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class UpdateMeDTO extends BaseDTO
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $account_id,
        public readonly ?string $first_name,
        public readonly ?string $last_name,
        public readonly ?string $email,
        public readonly ?string $timezone,
        public readonly ?string $password,
        public readonly ?string $current_password,
        public readonly ?string $locale,
    )
    {
    }
}
