<?php

namespace Tests\Feature\Http\Actions\EventSettings;

use HiEvents\DomainObjects\Status\EventStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class OfflinePaymentFollowUpSettingsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_new_events_use_order_references_and_let_buyers_report_payments_but_keep_payment_links_off(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEventWithSettings($owner, $accountId);

        $this->getJson("/events/$eventId/settings", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->assertJsonPath('data.offline_payment_reporting_enabled', true)
            ->assertJsonPath('data.offline_payment_order_reference_enabled', true)
            ->assertJsonPath('data.payment_links_enabled', false)
            ->assertJsonPath('data.payment_link_expiry_hours', 72);
    }

    public function test_the_settings_are_saved_and_served_on_the_public_event(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEventWithSettings($owner, $accountId);
        DB::table('events')->where('id', $eventId)->update(['status' => EventStatus::LIVE->name]);

        $this->patchJson("/events/$eventId/settings", [
            'offline_payment_reporting_enabled' => false,
            'offline_payment_order_reference_enabled' => false,
            'payment_links_enabled' => true,
            'payment_link_expiry_hours' => 168,
        ], $this->headersFor($owner, $accountId))->assertOk();

        $this->getJson("/public/events/$eventId")
            ->assertOk()
            ->assertJsonPath('data.settings.offline_payment_reporting_enabled', false)
            ->assertJsonPath('data.settings.offline_payment_order_reference_enabled', false)
            ->assertJsonPath('data.settings.payment_links_enabled', true)
            ->assertJsonPath('data.settings.payment_link_expiry_hours', 168);
    }

    public function test_the_hold_must_be_between_one_hour_and_thirty_days(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEventWithSettings($owner, $accountId);
        $headers = $this->headersFor($owner, $accountId);

        $this->patchJson("/events/$eventId/settings", ['payment_link_expiry_hours' => 0], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_link_expiry_hours');

        $this->patchJson("/events/$eventId/settings", ['payment_link_expiry_hours' => 721], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_link_expiry_hours');
    }

    private function createEventWithSettings($owner, int $accountId): int
    {
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizer_settings')->insert(['organizer_id' => $organizerId, 'created_at' => now(), 'updated_at' => now()]);

        return $this->postJson('/events', [
            'title' => 'Offline follow-up test',
            'type' => 'SINGLE',
            'organizer_id' => $organizerId,
            'start_date' => '2030-06-14 19:00',
            'currency' => 'EUR',
            'timezone' => 'UTC',
        ], $this->headersFor($owner, $accountId))->assertSuccessful()->json('data.id');
    }
}
