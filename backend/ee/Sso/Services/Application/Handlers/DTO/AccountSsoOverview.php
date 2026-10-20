<?php

namespace HiEvents\Enterprise\Sso\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use Illuminate\Support\Collection;

class AccountSsoOverview extends BaseDataObject
{
    /**
     * @param  Collection<int, AccountSsoDomainDomainObject>  $domains
     */
    public function __construct(
        public readonly ?AccountSsoConnectionDomainObject $connection,
        public readonly Collection $domains,
        public readonly string $redirectUri,
        public readonly int $ssoSignInsLast30Days,
    ) {}
}
