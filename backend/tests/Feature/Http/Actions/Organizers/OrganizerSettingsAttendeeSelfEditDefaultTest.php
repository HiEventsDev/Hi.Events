<?php

namespace Tests\Feature\Http\Actions\Organizers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class OrganizerSettingsAttendeeSelfEditDefaultTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_attendee_self_service_default_can_be_turned_off_and_stays_off(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizer_settings')->insert([
            'organizer_id' => $organizerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $headers = $this->headersFor($owner, $accountId);

        $this->getJson("/organizers/$organizerId/settings", $headers)
            ->assertOk()
            ->assertJsonPath('data.default_allow_attendee_self_edit', true);

        $this->patchJson("/organizers/$organizerId/settings", [
            'default_show_marketing_opt_in' => false,
            'default_allow_attendee_self_edit' => false,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.default_allow_attendee_self_edit', false);

        $this->patchJson("/organizers/$organizerId/settings", ['default_allow_attendee_self_edit' => null], $headers)
            ->assertOk()
            ->assertJsonPath('data.default_allow_attendee_self_edit', false);

        $this->getJson("/organizers/$organizerId/settings", $headers)
            ->assertOk()
            ->assertJsonPath('data.default_allow_attendee_self_edit', false);
    }
}
