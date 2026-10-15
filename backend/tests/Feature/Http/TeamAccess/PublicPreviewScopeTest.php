<?php

namespace Tests\Feature\Http\TeamAccess;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class PublicPreviewScopeTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_a_restricted_member_can_preview_only_the_draft_events_they_were_given(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $grantedEvent = $this->createEvent($accountId, $organizerId, 'Granted');
        $otherEvent = $this->createEvent($accountId, $organizerId, 'Other');
        DB::table('events')->whereIn('id', [$grantedEvent, $otherEvent])->update(['status' => 'DRAFT']);

        $member = $this->addRestrictedAccountUser($accountId, eventIds: [$grantedEvent]);
        $headers = $this->headersFor($member, $accountId);

        $this->getJson("/public/events/{$grantedEvent}", $headers)->assertOk();
        $this->getJson("/public/events/{$otherEvent}", $headers)->assertNotFound();
    }
}
