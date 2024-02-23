<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DataTransferObjects\BaseDTO;

class AcceptInvitationDTO extends BaseDTO
{
    public function __construct(
        public readonly string $invitation_token,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $password,
        public readonly string $timezone,
    )
    {
    }
}
