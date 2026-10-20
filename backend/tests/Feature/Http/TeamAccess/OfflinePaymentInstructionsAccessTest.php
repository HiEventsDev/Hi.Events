<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class OfflinePaymentInstructionsAccessTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use UsesApiKeys;

    private const SAVED_INSTRUCTIONS = '<p>Pay to IBAN IE00 OWNER 0000</p>';

    private const ATTACKER_INSTRUCTIONS = '<p>Pay to IBAN GB00 ATTACKER 9999</p>';

    private const MEMBER_REFUSAL = 'Only members who can manage payments can change the offline payment instructions.';

    private const API_KEY_REFUSAL = 'Offline payment instructions can only be changed from the dashboard, not with an API key.';

    private User $owner;

    private int $accountId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($this->accountId);
        DB::table('organizer_settings')->insert(['organizer_id' => $organizerId, 'created_at' => now(), 'updated_at' => now()]);

        $this->eventId = $this->postJson('/events', [
            'title' => 'Offline payments',
            'type' => 'SINGLE',
            'organizer_id' => $organizerId,
            'start_date' => '2030-06-14 19:00',
            'currency' => 'EUR',
            'timezone' => 'UTC',
        ], $this->headersFor($this->owner, $this->accountId))->assertSuccessful()->json('data.id');

        $this->patchJson("/events/{$this->eventId}/settings", [
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => self::SAVED_INSTRUCTIONS,
        ], $this->headersFor($this->owner, $this->accountId))->assertOk();
    }

    public function test_admins_can_change_the_offline_payment_instructions(): void
    {
        $admin = $this->addAccountUser($this->accountId, Role::ADMIN);

        $this->patchJson("/events/{$this->eventId}/settings", [
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => '<p>New bank details</p>',
        ], $this->headersFor($admin, $this->accountId))->assertOk();

        $this->assertSame('<p>New bank details</p>', $this->savedInstructions());
    }

    public function test_an_organizer_without_manage_payments_cannot_change_the_offline_payment_instructions(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->patchJson("/events/{$this->eventId}/settings", [
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => self::ATTACKER_INSTRUCTIONS,
        ], $this->headersFor($organizer, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offline_payment_instructions' => self::MEMBER_REFUSAL]);

        $this->patchJson("/events/{$this->eventId}/settings", [
            'payment_providers' => ['STRIPE'],
            'offline_payment_instructions' => null,
        ], $this->headersFor($organizer, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offline_payment_instructions' => self::MEMBER_REFUSAL]);

        $this->putJson("/events/{$this->eventId}/settings", $this->fullSettings([
            'offline_payment_instructions' => self::ATTACKER_INSTRUCTIONS,
        ]), $this->headersFor($organizer, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offline_payment_instructions' => self::MEMBER_REFUSAL]);

        $this->putJson("/events/{$this->eventId}/settings", $this->fullSettings([], without: 'offline_payment_instructions'), $this->headersFor($organizer, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offline_payment_instructions' => self::MEMBER_REFUSAL]);

        $this->assertSame(self::SAVED_INSTRUCTIONS, $this->savedInstructions());
        $this->assertSame(['STRIPE', 'OFFLINE'], $this->savedPaymentProviders());
    }

    public function test_an_organizer_without_manage_payments_can_save_other_settings_with_the_unchanged_instructions(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->patchJson("/events/{$this->eventId}/settings", [
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => self::SAVED_INSTRUCTIONS,
            'require_billing_address' => false,
            'enable_invoicing' => true,
            'organization_name' => 'Acme Events',
            'organization_address' => '<p>1 Main Street</p>',
        ], $this->headersFor($organizer, $this->accountId))->assertOk();

        $this->patchJson("/events/{$this->eventId}/settings", [
            'seo_title' => 'Updated title',
        ], $this->headersFor($organizer, $this->accountId))->assertOk();

        $this->putJson("/events/{$this->eventId}/settings", $this->fullSettings([
            'seo_title' => 'Round-tripped title',
        ]), $this->headersFor($organizer, $this->accountId))->assertOk();

        $settings = DB::table('event_settings')->where('event_id', $this->eventId)->first();
        $this->assertSame(self::SAVED_INSTRUCTIONS, $settings->offline_payment_instructions);
        $this->assertSame('Round-tripped title', $settings->seo_title);
        $this->assertFalse((bool) $settings->require_billing_address);
    }

    public function test_re_sending_instructions_that_purify_to_the_saved_value_is_not_a_change(): void
    {
        $this->patchJson("/events/{$this->eventId}/settings", [
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => '<p>Pay by transfer<script>alert(1)</script></p>',
        ], $this->headersFor($this->owner, $this->accountId))->assertOk();

        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->patchJson("/events/{$this->eventId}/settings", [
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => '<p>Pay by transfer<script>alert(1)</script></p>',
        ], $this->headersFor($organizer, $this->accountId))->assertOk();

        $this->assertSame('<p>Pay by transfer</p>', $this->savedInstructions());
    }

    public function test_an_api_key_cannot_change_the_instructions_even_for_an_admin(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $this->patchJson("/events/{$this->eventId}/settings", [
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => self::ATTACKER_INSTRUCTIONS,
        ], $this->apiKeyHeaders($apiKey))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offline_payment_instructions' => self::API_KEY_REFUSAL]);

        $this->putJson("/events/{$this->eventId}/settings", $this->fullSettings([
            'offline_payment_instructions' => self::ATTACKER_INSTRUCTIONS,
        ]), $this->apiKeyHeaders($apiKey))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offline_payment_instructions' => self::API_KEY_REFUSAL]);

        $this->assertSame(self::SAVED_INSTRUCTIONS, $this->savedInstructions());

        $this->patchJson("/events/{$this->eventId}/settings", [
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => self::SAVED_INSTRUCTIONS,
            'require_billing_address' => false,
        ], $this->apiKeyHeaders($apiKey))->assertOk();

        $this->patchJson("/events/{$this->eventId}/settings", [
            'seo_title' => 'Set with a key',
        ], $this->apiKeyHeaders($apiKey))->assertOk();

        $this->putJson("/events/{$this->eventId}/settings", $this->fullSettings([
            'seo_title' => 'Put with a key',
        ]), $this->apiKeyHeaders($apiKey))->assertOk();

        $this->assertSame(self::SAVED_INSTRUCTIONS, $this->savedInstructions());
    }

    public function test_blank_instructions_are_unchanged_when_none_are_saved(): void
    {
        $this->patchJson("/events/{$this->eventId}/settings", [
            'payment_providers' => ['STRIPE'],
            'offline_payment_instructions' => null,
        ], $this->headersFor($this->owner, $this->accountId))->assertOk();

        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $this->patchJson("/events/{$this->eventId}/settings", [
            'payment_providers' => ['STRIPE'],
            'offline_payment_instructions' => '',
        ], $this->apiKeyHeaders($apiKey))->assertOk();

        $this->patchJson("/events/{$this->eventId}/settings", [
            'offline_payment_instructions' => self::ATTACKER_INSTRUCTIONS,
        ], $this->apiKeyHeaders($apiKey))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offline_payment_instructions' => self::API_KEY_REFUSAL]);
    }

    private function savedInstructions(): ?string
    {
        return DB::table('event_settings')->where('event_id', $this->eventId)->value('offline_payment_instructions');
    }

    private function savedPaymentProviders(): array
    {
        return json_decode(DB::table('event_settings')->where('event_id', $this->eventId)->value('payment_providers'), true);
    }

    private function fullSettings(array $overrides, ?string $without = null): array
    {
        $settings = $this->getJson("/events/{$this->eventId}/settings", $this->headersFor($this->owner, $this->accountId))
            ->assertOk()
            ->json('data');

        $settings['require_attendee_details'] = $settings['required_attendee_details'];
        unset($settings['required_attendee_details']);

        $settings = array_merge($settings, $overrides);
        unset($settings[$without]);

        return $settings;
    }
}
