<?php

namespace HiEvents\Services\Application\Handlers\Account\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class UpdateAccountDTO extends BaseDTO
{
    public function __construct(
        public readonly string $account_id,
        public readonly string $updated_by_user_id,
        public readonly string $name,
        public readonly string $currency_code,
        public readonly string $timezone,
    )
    {
    }
}
