<?php

namespace HiEvents\Services\Handlers\Auth\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\UserDomainObject;
use Illuminate\Support\Collection;

class AuthenicatedResponseDTO extends BaseDTO
{
    public function __construct(
        public ?string          $token,
        public int              $expiresIn,
        public Collection       $accounts,
        public UserDomainObject $user,
    )
    {
    }
}
