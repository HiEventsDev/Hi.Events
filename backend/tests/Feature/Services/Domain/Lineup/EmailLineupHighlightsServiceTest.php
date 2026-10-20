<?php

namespace Tests\Feature\Services\Domain\Lineup;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Lineup\EmailLineupHighlightsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class EmailLineupHighlightsServiceTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_hidden_people_never_reach_the_email(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $eventId = $this->createEvent($accountId, $organizerId);
        DB::table('events')->where('id', $eventId)->update(['timezone' => 'Europe/Dublin']);

        $this->postJson("/events/$eventId/lineup/import", [
            'people' => [
                ['name' => 'Public Headliner', 'billing_tier' => 'HEADLINER'],
                ['name' => 'Secret Guest', 'billing_tier' => 'HEADLINER', 'is_hidden' => true],
            ],
            'slots' => [
                ['starts_at' => '2030-06-14 21:00:00', 'ends_at' => '2030-06-14 22:00:00', 'people' => ['Public Headliner']],
                ['starts_at' => '2030-06-14 23:00:00', 'ends_at' => '2030-06-15 00:00:00', 'people' => ['Secret Guest']],
            ],
        ], $this->headersFor($owner, $accountId))->assertOk();

        $highlights = app(EmailLineupHighlightsService::class)->build(
            app(EventRepositoryInterface::class)->findById($eventId),
            (new EventSettingDomainObject)->setHomepageSections(null),
        );

        $this->assertSame(['Public Headliner'], array_column($highlights->slots, 'title'));
        $this->assertStringNotContainsString('Secret Guest', json_encode($highlights->toArray()));
    }
}
