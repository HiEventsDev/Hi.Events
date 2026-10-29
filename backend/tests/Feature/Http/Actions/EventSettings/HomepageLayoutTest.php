<?php

namespace Tests\Feature\Http\Actions\EventSettings;

use HiEvents\DomainObjects\Status\EventStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class HomepageLayoutTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_the_chosen_layout_is_saved_and_served_on_the_public_event(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEvent($owner, $accountId);
        DB::table('events')->where('id', $eventId)->update(['status' => EventStatus::LIVE->name]);

        $this->patchJson("/events/$eventId/settings", [
            'homepage_theme_settings' => ['accent' => '#0f766e', 'background' => '#f0fdfa', 'mode' => 'light', 'layout' => 'FLYER'],
        ], $this->headersFor($owner, $accountId))->assertOk();

        $this->getJson("/public/events/$eventId")
            ->assertOk()
            ->assertJsonPath('data.settings.homepage_theme_settings.layout', 'FLYER');
    }

    public function test_unknown_layouts_are_rejected(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEvent($owner, $accountId);

        $this->patchJson("/events/$eventId/settings", [
            'homepage_theme_settings' => ['layout' => 'CAROUSEL'],
        ], $this->headersFor($owner, $accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['homepage_theme_settings.layout' => 'The selected layout is not supported.']);
    }

    private function createEvent($owner, int $accountId): int
    {
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizer_settings')->insert(['organizer_id' => $organizerId, 'created_at' => now(), 'updated_at' => now()]);

        return $this->postJson('/events', [
            'title' => 'Layout test',
            'type' => 'SINGLE',
            'organizer_id' => $organizerId,
            'start_date' => '2030-06-14 19:00',
            'currency' => 'EUR',
            'timezone' => 'UTC',
        ], $this->headersFor($owner, $accountId))->assertSuccessful()->json('data.id');
    }
}
