<?php

namespace HiEvents\Services\Application\Handlers\User\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\Role;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;

class CreateUserDTO extends BaseDataObject
{
    public function __construct(
        public string  $first_name,
        public ?string $last_name = null,
        public string  $email,
        public int     $invited_by,
        public int     $account_id,

        #[WithCast(EnumCast::class)]
        public Role    $role,
    )
    {
    }
}
