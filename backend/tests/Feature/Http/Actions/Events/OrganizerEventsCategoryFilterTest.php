<?php

namespace Tests\Feature\Http\Actions\Events;

use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class OrganizerEventsCategoryFilterTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_public_and_dashboard_event_lists_filter_by_category(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizers')->where('id', $organizerId)->update(['status' => OrganizerStatus::LIVE->name]);

        $concert = $this->createEvent($accountId, $organizerId, 'Concert');
        $gig = $this->createEvent($accountId, $organizerId, 'Gig');
        $talk = $this->createEvent($accountId, $organizerId, 'Talk');
        DB::table('events')->whereIn('id', [$concert, $gig, $talk])->update(['status' => EventStatus::LIVE->name]);
        DB::table('events')->whereIn('id', [$concert, $gig])->update(['category' => 'MUSIC']);
        DB::table('events')->where('id', $talk)->update(['category' => 'BUSINESS']);

        $music = $this->getJson("/public/organizers/$organizerId/events?filter_fields[category][eq]=MUSIC")
            ->assertOk()
            ->json('data.*.id');
        $this->assertEqualsCanonicalizing([$concert, $gig], $music);

        $either = $this->getJson("/public/organizers/$organizerId/events?filter_fields[category][in]=BUSINESS,MUSIC")
            ->assertOk()
            ->json('data.*.id');
        $this->assertEqualsCanonicalizing([$concert, $gig, $talk], $either);

        $dashboard = $this->getJson('/events?filter_fields[category][eq]=BUSINESS', $this->headersFor($owner, $accountId))
            ->assertOk()
            ->json('data.*.id');
        $this->assertSame([$talk], $dashboard);
    }
}
