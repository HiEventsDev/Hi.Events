<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $accountId;

    private int $organizerId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
        $this->eventId = $this->createEvent($this->accountId, $this->organizerId);
    }

    public function test_viewer_can_read_but_not_write(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);

        $this->getJson('/events', $this->headers($viewer))->assertOk();
        $this->getJson("/events/{$this->eventId}", $this->headers($viewer))->assertOk();
        $this->getJson("/events/{$this->eventId}/check-in-lists", $this->headers($viewer))->assertOk();

        $this->postJson("/events/{$this->eventId}/check-in-lists", [
            'name' => 'Door',
            'product_ids' => [],
        ], $this->headers($viewer))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'READ_ONLY_ROLE');

        $this->deleteJson("/events/{$this->eventId}", [], $this->headers($viewer))->assertForbidden();
    }

    public function test_viewer_can_still_manage_their_own_profile(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);

        $this->putJson('/users/me', [
            'first_name' => 'Renamed',
            'last_name' => 'Viewer',
            'email' => $viewer->email,
            'timezone' => 'UTC',
            'locale' => 'en',
        ], $this->headers($viewer))->assertOk();
    }

    public function test_organizer_cannot_use_admin_only_endpoints(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->getJson('/users', $this->headers($organizer))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');

        $this->deleteJson("/events/{$this->eventId}", [], $this->headers($organizer))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');

        $this->getJson("/organizers/{$this->organizerId}/stripe/connect_accounts", $this->headers($organizer))
            ->assertForbidden();
    }

    public function test_admin_can_use_admin_only_endpoints(): void
    {
        $admin = $this->addAccountUser($this->accountId, Role::ADMIN);

        $this->getJson('/users', $this->headers($admin))->assertOk();
    }

    public function test_me_exposes_effective_access(): void
    {
        $viewer = $this->addRestrictedAccountUser(
            $this->accountId,
            Role::VIEWER,
            eventIds: [$this->eventId],
            permissions: ['VIEW_FINANCIALS' => true],
        );

        $this->getJson('/users/me', $this->headers($viewer))
            ->assertOk()
            ->assertJsonPath('data.access.scope', 'RESTRICTED')
            ->assertJsonPath('data.access.event_ids', [$this->eventId])
            ->assertJsonPath('data.access.permissions', ['VIEW_FINANCIALS']);
    }

    private function headers(User $user): array
    {
        return $this->headersFor($user, $this->accountId);
    }
}
