<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class TagManagerAccessTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private User $owner;

    private int $accountId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => false]);
        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->postJson('/organizers', [
            'name' => 'Organizer',
            'email' => 'organizer-'.uniqid().'@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ], $this->headersFor($this->owner, $this->accountId))->assertCreated()->json('data.id');
    }

    public function test_only_account_admins_can_add_a_tag_manager_container(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->patchJson("/organizers/{$this->organizerId}/settings", $this->tagManager('GTM-ATTACKER'), $this->headersFor($organizer, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tracking_pixels.0.provider');

        $this->patchJson("/organizers/{$this->organizerId}/settings", $this->tagManager('GTM-OWNER1'), $this->headersFor($this->owner, $this->accountId))
            ->assertOk();

        $this->patchJson("/organizers/{$this->organizerId}/settings", $this->tagManager('GTM-OWNER1'), $this->headersFor($organizer, $this->accountId))
            ->assertOk();
    }

    private function tagManager(string $containerId): array
    {
        return [
            'tracking_pixels' => [['provider' => 'google_tag_manager', 'pixel_id' => $containerId, 'enabled' => true]],
            'tracking_consent_acknowledged' => true,
        ];
    }
}
