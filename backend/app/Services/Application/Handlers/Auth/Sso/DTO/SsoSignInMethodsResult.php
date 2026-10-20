<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\UserIdentityDomainObject;
use HiEvents\Enterprise\Sso\Services\Domain\DTO\LinkableSsoConnection;
use Illuminate\Support\Collection;

class SsoSignInMethodsResult extends BaseDataObject
{
    /**
     * @param  Collection<int, UserIdentityDomainObject>  $identities
     * @param  Collection<int, LinkableSsoConnection>  $linkableConnections
     */
    public function __construct(
        public readonly Collection $identities,
        public readonly Collection $linkableConnections,
    ) {}
}
