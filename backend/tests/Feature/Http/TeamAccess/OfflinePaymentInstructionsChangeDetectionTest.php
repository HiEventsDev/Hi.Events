<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class OfflinePaymentInstructionsChangeDetectionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use UsesApiKeys;

    private const PADDED_INSTRUCTIONS = "  <p>Pay to IBAN IE00 OWNER 0000</p>\n";

    private const MEMBER_REFUSAL = 'Only members who can manage payments can change the offline payment instructions.';

    private User $owner;

    private int $accountId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
        DB::table('organizer_settings')->insert(['organizer_id' => $this->organizerId, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_re_sending_saved_instructions_that_have_surrounding_whitespace_is_not_a_change(): void
    {
        $eventId = $this->createEventWithInstructions(self::PADDED_INSTRUCTIONS);
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $saved = $this->getJson("/events/$eventId/settings", $this->headersFor($organizer, $this->accountId))
            ->assertOk()
            ->json('data.offline_payment_instructions');
        $this->assertSame(self::PADDED_INSTRUCTIONS, $saved);

        $this->patchJson("/events/$eventId/settings", [
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => $saved,
            'seo_title' => 'Updated by an organizer',
        ], $this->headersFor($organizer, $this->accountId))->assertOk();

        $this->assertSame('Updated by an organizer', DB::table('event_settings')->where('event_id', $eventId)->value('seo_title'));

        $this->putJson("/events/$eventId/settings", $this->fullSettings($eventId, [
            'seo_title' => 'Put by an organizer',
        ]), $this->headersFor($organizer, $this->accountId))->assertOk();

        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        $this->patchJson("/events/$eventId/settings", [
            'offline_payment_instructions' => self::PADDED_INSTRUCTIONS,
        ], $this->apiKeyHeaders($apiKey))->assertOk();
    }

    public function test_a_real_change_to_padded_saved_instructions_is_still_refused(): void
    {
        $eventId = $this->createEventWithInstructions(self::PADDED_INSTRUCTIONS);
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->patchJson("/events/$eventId/settings", [
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => '<p>Pay to IBAN GB00 ATTACKER 9999</p>',
        ], $this->headersFor($organizer, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offline_payment_instructions' => self::MEMBER_REFUSAL]);

        $this->patchJson("/events/$eventId/settings", [
            'payment_providers' => ['STRIPE'],
            'offline_payment_instructions' => null,
        ], $this->headersFor($organizer, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offline_payment_instructions' => self::MEMBER_REFUSAL]);

        $this->assertSame(self::PADDED_INSTRUCTIONS, DB::table('event_settings')->where('event_id', $eventId)->value('offline_payment_instructions'));
    }

    public function test_a_member_without_access_to_the_event_is_refused_the_same_way_whether_or_not_instructions_are_saved(): void
    {
        $accessibleEventId = $this->createEventWithInstructions(null);
        $withInstructions = $this->createEventWithInstructions('<p>Pay to IBAN IE00 OWNER 0000</p>');
        $withoutInstructions = $this->createEventWithInstructions(null);
        $member = $this->addRestrictedAccountUser($this->accountId, eventIds: [$accessibleEventId]);

        foreach ([$withInstructions, $withoutInstructions] as $eventId) {
            $this->patchJson("/events/$eventId/settings", [
                'payment_providers' => ['STRIPE'],
                'offline_payment_instructions' => null,
            ], $this->headersFor($member, $this->accountId))
                ->assertForbidden()
                ->assertJsonMissingPath('errors.offline_payment_instructions');
        }

        $this->patchJson("/events/$accessibleEventId/settings", [
            'payment_providers' => ['STRIPE', 'OFFLINE'],
            'offline_payment_instructions' => '<p>Pay to IBAN GB00 ATTACKER 9999</p>',
        ], $this->headersFor($member, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offline_payment_instructions' => self::MEMBER_REFUSAL]);

        $this->assertSame('<p>Pay to IBAN IE00 OWNER 0000</p>', DB::table('event_settings')->where('event_id', $withInstructions)->value('offline_payment_instructions'));
        $this->assertNull(DB::table('event_settings')->where('event_id', $accessibleEventId)->value('offline_payment_instructions'));
    }

    private function createEventWithInstructions(?string $instructions): int
    {
        $eventId = $this->postJson('/events', [
            'title' => 'Offline payments',
            'type' => 'SINGLE',
            'organizer_id' => $this->organizerId,
            'start_date' => '2030-06-14 19:00',
            'currency' => 'EUR',
            'timezone' => 'UTC',
        ], $this->headersFor($this->owner, $this->accountId))->assertSuccessful()->json('data.id');

        DB::table('event_settings')->where('event_id', $eventId)->update([
            'payment_providers' => json_encode($instructions === null ? ['STRIPE'] : ['STRIPE', 'OFFLINE']),
            'offline_payment_instructions' => $instructions,
        ]);

        return $eventId;
    }

    private function fullSettings(int $eventId, array $overrides): array
    {
        $settings = $this->getJson("/events/$eventId/settings", $this->headersFor($this->owner, $this->accountId))
            ->assertOk()
            ->json('data');

        $settings['require_attendee_details'] = $settings['required_attendee_details'];
        unset($settings['required_attendee_details']);

        return array_merge($settings, $overrides);
    }
}
