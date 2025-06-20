<?php

namespace HiEvents\Services\Application\Handlers\Auth\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class AcceptInvitationDTO extends BaseDTO
{
    public function __construct(
        public readonly string  $invitation_token,
        public readonly string  $first_name,
        public readonly ?string $last_name = null,
        public readonly string  $password,
        public readonly string  $timezone,
    )
    {
    }
}
