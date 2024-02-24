<?php

namespace HiEvents\Service\Common\Auth\DTO;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\DataTransferObjects\BaseDTO;

class LoginResponse extends BaseDTO
{
    public function __construct(
        public readonly string           $token,
        public readonly UserDomainObject $user,
    )
    {
    }
}
