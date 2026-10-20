<?php

namespace HiEvents\Services\Application\Handlers\User\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Services\Domain\Auth\Access\DTO\AccountUserAccessData;

class UpdateUserDTO extends BaseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $account_id,
        public readonly string $first_name,
        public readonly int $updated_by_user_id,
        public readonly ?string $last_name = null,
        public readonly ?Role $role = null,
        public readonly ?UserStatus $status = null,
        public readonly ?AccountUserAccessData $access = null,
    ) {}
}
