<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\AccountUserAccessGrantDomainObject;
use HiEvents\Repository\Eloquent\AccountUserAccessGrantRepository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class AccountUserAccessGrantRepositoryTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_returns_only_grants_whose_event_or_organizer_is_live(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $liveOrganizer = $this->createOrganizer($accountId);
        $deletedOrganizer = $this->createOrganizer($accountId);
        $liveEvent = $this->createEvent($accountId, $liveOrganizer);
        $deletedEvent = $this->createEvent($accountId, $liveOrganizer);

        $member = $this->addRestrictedAccountUser(
            $accountId,
            organizerIds: [$liveOrganizer, $deletedOrganizer],
            eventIds: [$liveEvent, $deletedEvent],
        );
        $otherMember = $this->addRestrictedAccountUser($accountId, eventIds: [$liveEvent]);

        DB::table('events')->where('id', $deletedEvent)->update(['deleted_at' => now()]);
        DB::table('organizers')->where('id', $deletedOrganizer)->update(['deleted_at' => now()]);

        $grants = $this->app->make(AccountUserAccessGrantRepository::class)
            ->findLiveGrantsForAccountUsers([$this->accountUserId($member, $accountId)]);

        $this->assertEqualsCanonicalizing(
            [['event', $liveEvent], ['organizer', $liveOrganizer]],
            $grants->map(fn (AccountUserAccessGrantDomainObject $grant) => $grant->getEventId() !== null
                ? ['event', $grant->getEventId()]
                : ['organizer', $grant->getOrganizerId()])->all(),
        );
        $this->assertNotContains($this->accountUserId($otherMember, $accountId), $grants->map->getAccountUserId()->all());
    }

    public function test_checks_targets_with_correlated_lookups_instead_of_scanning_every_event_and_organizer(): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->app->make(AccountUserAccessGrantRepository::class)->findLiveGrantsForAccountUsers([1]);

        $sql = collect($queries)->first(fn (string $sql) => str_contains($sql, 'account_user_access_grants'));

        $this->assertNotNull($sql);
        $this->assertStringNotContainsString('in (select', $sql);
        $this->assertStringContainsString('"events"."id" = "account_user_access_grants"."event_id"', $sql);
        $this->assertStringContainsString('"organizers"."id" = "account_user_access_grants"."organizer_id"', $sql);
    }
}
