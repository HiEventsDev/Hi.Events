<?php

namespace HiEvents\Services\Domain\Auth\Access\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\TeamAccessVia;

class TeamMemberAccessDTO extends BaseDataObject
{
    public function __construct(
        public int $user_id,
        public string $first_name,
        public ?string $last_name,
        public string $email,
        public string $role,
        public string $status,
        public TeamAccessVia $access_via,
    ) {}
}
