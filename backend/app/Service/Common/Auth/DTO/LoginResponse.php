<?php

namespace TicketKitten\Service\Common\Auth\DTO;

use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\DataTransferObjects\BaseDTO;

class LoginResponse extends BaseDTO
{
    public function __construct(
        public readonly string           $token,
        public readonly UserDomainObject $user,
    )
    {
    }
}
