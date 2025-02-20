<?php

namespace HiEvents\Services\Application\Handlers\Auth\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\UserDomainObject;
use Illuminate\Support\Collection;

class AuthenticatedResponseDTO extends BaseDTO
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
