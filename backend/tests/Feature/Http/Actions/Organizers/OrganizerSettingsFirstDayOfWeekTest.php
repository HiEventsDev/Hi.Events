<?php

namespace Tests\Feature\Http\Actions\Organizers;

use HiEvents\DomainObjects\Status\OrganizerStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class OrganizerSettingsFirstDayOfWeekTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_defaults_to_monday(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizerWithSettings($accountId);

        $this->getJson("/organizers/$organizerId/settings", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->assertJsonPath('data.first_day_of_week', 1);
    }

    public function test_saves_first_day_of_week_and_keeps_it_when_other_settings_change(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizerWithSettings($accountId);
        $headers = $this->headersFor($owner, $accountId);

        $this->patchJson("/organizers/$organizerId/settings", ['first_day_of_week' => 0], $headers)
            ->assertOk()
            ->assertJsonPath('data.first_day_of_week', 0);

        $this->patchJson("/organizers/$organizerId/settings", ['seo_title' => 'Promoter'], $headers)
            ->assertOk()
            ->assertJsonPath('data.first_day_of_week', 0);
    }

    public function test_rejects_an_invalid_day(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizerWithSettings($accountId);

        $this->patchJson("/organizers/$organizerId/settings", ['first_day_of_week' => 7], $this->headersFor($owner, $accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('first_day_of_week');
    }

    public function test_public_organizer_exposes_first_day_of_week(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizerWithSettings($accountId, firstDayOfWeek: 6);
        DB::table('organizers')->where('id', $organizerId)->update(['status' => OrganizerStatus::LIVE->name]);

        $this->getJson("/public/organizers/$organizerId")
            ->assertOk()
            ->assertJsonPath('data.settings.first_day_of_week', 6);
    }

    public function test_event_scoped_member_gets_first_day_of_week_without_private_organizer_settings(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizerWithSettings($accountId, firstDayOfWeek: 0);
        DB::table('organizer_settings')->where('organizer_id', $organizerId)->update(['homepage_password' => 'secret']);
        $eventId = $this->createEvent($accountId, $organizerId);
        $member = $this->addRestrictedAccountUser($accountId, eventIds: [$eventId]);

        $response = $this->getJson("/events/$eventId", $this->headersFor($member, $accountId))
            ->assertOk()
            ->assertJsonPath('data.organizer.settings.first_day_of_week', 0);

        $this->assertArrayNotHasKey('homepage_password', $response->json('data.organizer.settings'));
    }

    private function createOrganizerWithSettings(int $accountId, int $firstDayOfWeek = 1): int
    {
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizer_settings')->insert([
            'organizer_id' => $organizerId,
            'first_day_of_week' => $firstDayOfWeek,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $organizerId;
    }
}
