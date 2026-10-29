<?php

namespace Tests\Feature\Http\Actions\Lineup;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class LineupHtmlPurificationTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $organizerId;

    private int $eventId;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        [$owner, $accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($accountId);
        $this->eventId = $this->createEvent($accountId, $this->organizerId);
        $this->headers = $this->headersFor($owner, $accountId);
    }

    public function test_import_strips_unsafe_html_from_bios_and_slot_descriptions(): void
    {
        $existingProfileId = $this->postJson("/organizers/$this->organizerId/profiles", [
            'name' => 'Nova Lights',
            'bio' => '<p>Old bio</p>',
        ], $this->headers)->assertCreated()->json('data.id');

        $this->postJson("/events/$this->eventId/lineup/import", [
            'people' => [
                ['name' => 'Nova Lights', 'bio' => '<p>New bio<img src="x" onerror="alert(1)"></p>'],
                ['name' => 'Ada Byron', 'bio' => '<p>Hello<script>alert(1)</script></p>'],
                ['name' => 'Blank Bio', 'bio' => '<script>alert(1)</script>'],
            ],
            'slots' => [
                [
                    'starts_at' => '2030-06-14 19:00:00',
                    'ends_at' => '2030-06-14 19:30:00',
                    'title' => 'Doors',
                    'description' => '<p>Doors open <a href="javascript:alert(1)" onclick="steal()">here</a></p>',
                ],
                [
                    'starts_at' => '2030-06-14 20:00:00',
                    'ends_at' => '2030-06-14 20:30:00',
                    'title' => 'Empty notes',
                    'description' => '<script>alert(1)</script>',
                ],
            ],
        ], $this->headers)
            ->assertOk()
            ->assertJson(['people_created' => 2, 'people_updated' => 1, 'slots_created' => 2]);

        $updatedBio = DB::table('profiles')->where('id', $existingProfileId)->value('bio');
        $this->assertStringContainsString('New bio', $updatedBio);
        $this->assertStringNotContainsString('onerror', $updatedBio);

        $this->assertSame('<p>Hello</p>', $this->bioFor('Ada Byron'));
        $this->assertNull($this->bioFor('Blank Bio'));

        $doors = $this->slotDescription('Doors');
        $this->assertStringContainsString('Doors open', $doors);
        $this->assertStringNotContainsString('javascript:', $doors);
        $this->assertStringNotContainsString('onclick', $doors);
        $this->assertNull($this->slotDescription('Empty notes'));
    }

    public function test_schedule_items_strip_unsafe_html_and_store_empty_descriptions_as_null(): void
    {
        $slotId = $this->postJson("/events/$this->eventId/lineup/schedule-items", [
            'title' => 'Doors',
            'description' => '<p>Notes<img src="x" onerror="alert(1)"><script>alert(2)</script></p>',
            'starts_at' => '2030-06-14 19:00',
            'ends_at' => '2030-06-14 20:00',
        ], $this->headers)->assertCreated()->json('data.id');

        $description = $this->slotDescription('Doors');
        $this->assertStringContainsString('Notes', $description);
        $this->assertStringNotContainsString('onerror', $description);
        $this->assertStringNotContainsString('<script', $description);

        $this->putJson("/events/$this->eventId/lineup/schedule-items/$slotId", [
            'title' => 'Doors',
            'description' => '<script>alert(1)</script>',
            'starts_at' => '2030-06-14 19:00',
            'ends_at' => '2030-06-14 20:00',
        ], $this->headers)->assertOk();

        $this->assertNull($this->slotDescription('Doors'));
    }

    private function bioFor(string $name): ?string
    {
        return DB::table('profiles')->where(['organizer_id' => $this->organizerId, 'name' => $name])->value('bio');
    }

    private function slotDescription(string $title): ?string
    {
        return DB::table('event_schedule_items')->where(['event_id' => $this->eventId, 'title' => $title])->value('description');
    }
}
