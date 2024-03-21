<?php

namespace HiEvents\Services\Domain\Auth\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\UserDomainObject;
use Illuminate\Support\Collection;

class LoginResponse extends BaseDTO
{
    public function __construct(
        public Collection                $accounts,
        public readonly ?string          $token,
        public readonly UserDomainObject $user,
        public readonly ?int             $accountId = null,
    )
    {
    }
}
