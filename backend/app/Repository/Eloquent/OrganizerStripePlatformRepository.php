<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\Models\OrganizerStripePlatform;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<OrganizerStripePlatformDomainObject>
 */
class OrganizerStripePlatformRepository extends BaseRepository implements OrganizerStripePlatformRepositoryInterface
{
    protected function getModel(): string
    {
        return OrganizerStripePlatform::class;
    }

    public function getDomainObject(): string
    {
        return OrganizerStripePlatformDomainObject::class;
    }

    public function findReusableForAccount(int $accountId, int $excludeOrganizerId, ?string $excludeStripeAccountId): Collection
    {
        return $this->db->table('organizer_stripe_platforms')
            ->join('organizers', 'organizer_stripe_platforms.organizer_id', '=', 'organizers.id')
            ->whereNotNull('organizer_stripe_platforms.stripe_setup_completed_at')
            ->whereNull('organizer_stripe_platforms.deleted_at')
            ->whereNull('organizers.deleted_at')
            ->where('organizers.account_id', $accountId)
            ->where('organizer_stripe_platforms.organizer_id', '!=', $excludeOrganizerId)
            ->when($excludeStripeAccountId !== null, fn($q) => $q->where('organizer_stripe_platforms.stripe_account_id', '!=', $excludeStripeAccountId))
            ->whereNotNull('organizer_stripe_platforms.stripe_account_id')
            ->select([
                'organizer_stripe_platforms.organizer_id as organizer_id',
                'organizer_stripe_platforms.stripe_account_id as stripe_account_id',
                'organizer_stripe_platforms.stripe_connect_platform as stripe_connect_platform',
                'organizer_stripe_platforms.stripe_account_details as stripe_account_details',
                'organizers.name as organizer_name',
            ])
            ->get();
    }
}
