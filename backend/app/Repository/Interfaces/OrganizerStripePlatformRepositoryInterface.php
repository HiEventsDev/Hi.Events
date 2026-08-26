<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<OrganizerStripePlatformDomainObject>
 */
interface OrganizerStripePlatformRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, object{organizer_id:int,organizer_name:string,stripe_account_id:string,stripe_connect_platform:?string,stripe_account_details:mixed}>
     */
    public function findReusableForAccount(int $accountId, int $excludeOrganizerId, ?string $excludeStripeAccountId): Collection;
}
