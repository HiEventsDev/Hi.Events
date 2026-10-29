<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class RestrictedAccessTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $accountId;

    private int $organizerA;

    private int $organizerB;

    private User $owner;

    private int $eventA1;

    private int $eventA2;

    private int $eventB1;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerA = $this->postJson('/organizers', [
            'name' => 'Organizer A',
            'email' => 'organizer-a@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ], $this->headersFor($this->owner, $this->accountId))->assertCreated()->json('data.id');
        $this->organizerB = $this->createOrganizer($this->accountId, 'Organizer B');
        $this->eventA1 = $this->createEvent($this->accountId, $this->organizerA, 'A1');
        $this->eventA2 = $this->createEvent($this->accountId, $this->organizerA, 'A2');
        $this->eventB1 = $this->createEvent($this->accountId, $this->organizerB, 'B1');
    }

    public function test_event_grant_lists_and_opens_only_that_event(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA1]);

        $this->assertSame([$this->eventA1], $this->listedEventIds($user));

        $this->getJson("/events/{$this->eventA1}", $this->headers($user))->assertOk();
        $this->getJson("/events/{$this->eventA2}", $this->headers($user))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'ACCESS_RESTRICTED');
        $this->getJson("/events/{$this->eventB1}", $this->headers($user))->assertForbidden();
    }

    public function test_organizer_grant_covers_every_event_of_that_organizer_including_new_ones(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerA]);
        $eventA3 = $this->createEvent($this->accountId, $this->organizerA, 'A3');

        $this->assertEqualsCanonicalizing([$this->eventA1, $this->eventA2, $eventA3], $this->listedEventIds($user));

        $this->getJson("/events/{$eventA3}", $this->headers($user))->assertOk();
        $this->getJson("/organizers/{$this->organizerA}/stats", $this->headers($user))->assertOk();
        $this->getJson("/events/{$this->eventB1}", $this->headers($user))->assertForbidden();
        $this->getJson("/organizers/{$this->organizerB}", $this->headers($user))->assertForbidden();
    }

    public function test_event_grant_can_read_parent_organizer_basics_but_not_organizer_wide_data(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA1]);

        $organizerIds = collect($this->getJson('/organizers', $this->headers($user))->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$this->organizerA], $organizerIds);

        $this->getJson("/organizers/{$this->organizerA}", $this->headers($user))->assertOk();
        $this->getJson("/organizers/{$this->organizerA}/locations", $this->headers($user))->assertOk();
        $this->getJson("/organizers/{$this->organizerA}/stats", $this->headers($user))->assertForbidden();
        $this->getJson("/organizers/{$this->organizerA}/orders", $this->headers($user))->assertForbidden();
        $this->getJson("/organizers/{$this->organizerA}/webhooks", $this->headers($user))->assertForbidden();
        $this->getJson("/organizers/{$this->organizerA}/widgets", $this->headers($user))->assertForbidden();
        $this->getJson("/organizers/{$this->organizerB}", $this->headers($user))->assertForbidden();
    }

    public function test_restricted_user_with_no_remaining_grants_sees_nothing(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA1]);

        DB::table('account_user_access_grants')->where('event_id', $this->eventA1)->delete();

        $this->assertSame([], $this->listedEventIds($user));
        $this->getJson("/events/{$this->eventA2}", $this->headers($user))->assertForbidden();
        $this->assertSame([], $this->getJson('/organizers', $this->headers($user))->json('data'));
    }

    public function test_creating_events_requires_an_organizer_grant(): void
    {
        $eventOnly = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA1]);
        $organizerGrant = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerA]);

        $this->postJson('/events', $this->eventPayload($this->organizerA), $this->headers($eventOnly))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'ACCESS_RESTRICTED');

        $this->postJson('/events', $this->eventPayload($this->organizerB), $this->headers($organizerGrant))
            ->assertForbidden();

        $this->postJson('/events', $this->eventPayload($this->organizerA), $this->headers($organizerGrant))
            ->assertOk()
            ->assertJsonPath('data.organizer_id', $this->organizerA);
    }

    public function test_restricted_user_cannot_create_organizers_or_change_account_taxes_but_can_read_them(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerA]);

        $this->postJson('/organizers', [
            'name' => 'New Organizer',
            'email' => 'new-organizer@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ], $this->headers($user))->assertForbidden();

        $this->postJson("/accounts/{$this->accountId}/taxes-and-fees", [
            'name' => 'VAT',
            'calculation_type' => 'PERCENTAGE',
            'type' => 'TAX',
            'rate' => 20,
            'is_active' => true,
            'is_default' => false,
        ], $this->headers($user))->assertForbidden();

        $this->getJson("/accounts/{$this->accountId}/taxes-and-fees", $this->headers($user))->assertOk();
        $this->getJson('/accounts', $this->headers($user))->assertOk();
    }

    public function test_restricted_user_cannot_delete_an_image_of_an_event_outside_their_scope(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA1]);

        $imageId = DB::table('images')->insertGetId([
            'account_id' => $this->accountId,
            'entity_id' => $this->eventB1,
            'entity_type' => 'HiEvents\\DomainObjects\\EventDomainObject',
            'type' => 'EVENT_COVER',
            'filename' => 'cover.png',
            'disk' => 'public',
            'path' => 'cover.png',
            'size' => 1,
            'mime_type' => 'image/png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/images/{$imageId}", [], $this->headers($user))->assertForbidden();
        $this->assertNotNull(DB::table('images')->where('id', $imageId)->whereNull('deleted_at')->value('id'));
    }

    public function test_account_wide_member_keeps_full_access(): void
    {
        $user = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->assertEqualsCanonicalizing(
            [$this->eventA1, $this->eventA2, $this->eventB1],
            $this->listedEventIds($user),
        );
    }

    public function test_grants_on_deleted_events_and_organizers_drop_out_so_the_member_stays_editable(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerB], eventIds: [$this->eventA1, $this->eventA2]);

        DB::table('events')->where('id', $this->eventA2)->update(['deleted_at' => now()]);
        DB::table('organizers')->where('id', $this->organizerB)->update(['deleted_at' => now()]);

        $ownerHeaders = $this->headersFor($this->owner, $this->accountId);
        $member = collect($this->getJson('/users', $ownerHeaders)->assertOk()->json('data'))->firstWhere('id', $user->id);

        $this->assertSame([], $member['access']['organizer_ids']);
        $this->assertSame([$this->eventA1], $member['access']['event_ids']);

        $this->putJson("/users/{$user->id}", [
            'first_name' => 'Still',
            'last_name' => 'Editable',
            'role' => 'ORGANIZER',
            'access_scope' => 'RESTRICTED',
            'organizer_ids' => $member['access']['organizer_ids'],
            'event_ids' => $member['access']['event_ids'],
        ], $ownerHeaders)->assertOk();
    }

    private function listedEventIds(User $user): array
    {
        return collect($this->getJson('/events', $this->headers($user))->assertOk()->json('data'))
            ->pluck('id')
            ->all();
    }

    private function headers(User $user): array
    {
        return $this->headersFor($user, $this->accountId);
    }

    private function eventPayload(int $organizerId): array
    {
        return [
            'title' => 'Created Event',
            'organizer_id' => $organizerId,
            'start_date' => now()->addMonth()->format('Y-m-d H:i'),
            'timezone' => 'UTC',
            'currency' => 'USD',
        ];
    }
}
