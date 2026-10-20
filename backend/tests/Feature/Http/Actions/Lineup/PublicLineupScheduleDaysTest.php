<?php

namespace Tests\Feature\Http\Actions\Lineup;

use HiEvents\DomainObjects\Status\EventStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class PublicLineupScheduleDaysTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [$owner, $accountId] = $this->createAccountOwner();
        $this->eventId = $this->createEvent($accountId, $this->createOrganizer($accountId));
        DB::table('events')->where('id', $this->eventId)->update([
            'timezone' => 'America/New_York',
            'type' => 'RECURRING',
            'status' => EventStatus::LIVE->name,
        ]);

        $this->postJson("/events/$this->eventId/lineup/import", [
            'people' => [
                ['name' => 'Opening Act'],
                ['name' => 'Second Week Act'],
                ['name' => 'Resident Host'],
                ['name' => 'Secret Guest', 'is_hidden' => true],
            ],
            'slots' => [
                ['starts_at' => '2030-06-14 20:00:00', 'ends_at' => '2030-06-14 21:00:00', 'people' => ['Opening Act']],
                ['starts_at' => '2030-06-15 01:30:00', 'ends_at' => '2030-06-15 02:30:00', 'people' => ['Opening Act']],
                ['starts_at' => '2030-06-28 19:00:00', 'ends_at' => '2030-06-28 20:00:00', 'people' => ['Opening Act']],
                ['starts_at' => '2030-06-21 20:00:00', 'ends_at' => '2030-06-21 21:00:00', 'people' => ['Second Week Act', 'Secret Guest']],
            ],
        ], $this->headersFor($owner, $accountId))->assertOk();
    }

    public function test_a_hidden_schedule_still_says_which_dates_each_person_is_on(): void
    {
        $this->setSections([['id' => 'schedule', 'type' => 'SCHEDULE', 'enabled' => false]]);

        $lineup = $this->getJson("/public/events/$this->eventId/lineup")->assertOk();

        $this->assertSame([], $lineup->json('data.schedule_items'));
        $this->assertSame(
            [
                'Opening Act' => ['2030-06-14', '2030-06-28'],
                'Second Week Act' => ['2030-06-21'],
                'Resident Host' => [],
            ],
            collect($lineup->json('data.entries'))->mapWithKeys(fn (array $entry) => [$entry['profile']['name'] => $entry['schedule_days']])->all(),
        );
        $this->assertStringNotContainsString('2030-06-15', $lineup->getContent());
    }

    public function test_a_hidden_schedule_on_a_single_date_event_reveals_no_dates(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['type' => 'SINGLE']);
        $this->setSections([['id' => 'schedule', 'type' => 'SCHEDULE', 'enabled' => false]]);

        $lineup = $this->getJson("/public/events/$this->eventId/lineup")
            ->assertOk()
            ->assertJsonCount(3, 'data.entries')
            ->assertJsonMissingPath('data.entries.0.schedule_days');

        $this->assertSame([], $lineup->json('data.schedule_items'));
        $this->assertStringNotContainsString('2030-06-', $lineup->getContent());
    }

    public function test_schedule_days_are_left_out_when_the_schedule_is_shown(): void
    {
        $this->setSections([['id' => 'schedule', 'type' => 'SCHEDULE', 'enabled' => true]]);

        $this->getJson("/public/events/$this->eventId/lineup")
            ->assertOk()
            ->assertJsonCount(4, 'data.schedule_items')
            ->assertJsonMissingPath('data.entries.0.schedule_days');
    }

    private function setSections(array $sections): void
    {
        DB::table('event_settings')->updateOrInsert(
            ['event_id' => $this->eventId],
            ['homepage_sections' => json_encode($sections), 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
