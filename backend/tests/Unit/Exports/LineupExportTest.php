<?php

namespace Tests\Unit\Exports;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Exports\Lineup\LineupExport;
use HiEvents\Services\Application\Handlers\Lineup\DTO\EventLineupDTO;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LineupExportTest extends TestCase
{
    private function export(): LineupExport
    {
        $nova = (new EventLineupEntryDomainObject)->setId(1)->setBillingTier('HEADLINER')->setRoleLabel('Closer')->setIsHidden(false)
            ->setProfile((new ProfileDomainObject)->setId(11)->setName('Nova')->setTagline('Techno')->setBio('<p>Loud &amp; <strong>late</strong></p><p>Berlin</p>')
                ->setLinks([['type' => 'SPOTIFY', 'url' => 'https://open.spotify.com/artist/x'], ['type' => 'WEBSITE', 'url' => 'https://nova.example.com']])
                ->setImages(new Collection([(new ImageDomainObject)->setId(5)])));
        $secret = (new EventLineupEntryDomainObject)->setId(2)->setBillingTier('STANDARD')->setRoleLabel(null)->setIsHidden(true)
            ->setProfile((new ProfileDomainObject)->setId(12)->setName('Secret')->setTagline(null)->setBio(null)->setLinks([])->setImages(new Collection));

        $performer = fn (int $entryId) => (new EventScheduleItemPerformerDomainObject)->setEventLineupEntryId($entryId);
        $item = fn (int $id, ?int $spaceId, string $start, string $end, ?string $title, array $entryIds) => (new EventScheduleItemDomainObject)
            ->setId($id)->setEventSpaceId($spaceId)->setStartsAt($start)->setEndsAt($end)->setTitle($title)->setDescription(null)
            ->setPerformers(new Collection(array_map($performer, $entryIds)));

        return (new LineupExport)->withData(new EventLineupDTO(
            entries: new Collection([$nova, $secret]),
            spaces: new Collection([
                (new EventSpaceDomainObject)->setId(20)->setName('Main Stage'),
                (new EventSpaceDomainObject)->setId(21)->setName('Tent'),
            ]),
            schedule_items: new Collection([
                $item(30, 21, '2030-06-14 18:00:00', '2030-06-14 19:00:00', 'Panel', [1, 2]),
                $item(31, 20, '2030-06-14 22:00:00', '2030-06-14 23:30:00', null, [1]),
                $item(32, 20, '2030-06-14 17:00:00', '2030-06-14 17:30:00', 'Doors', []),
                $item(33, null, '2030-06-15 10:00:00', '2030-06-15 11:00:00', 'Yoga', []),
            ]),
        ), 'Europe/Dublin');
    }

    public function test_schedule_rows_are_local_times_grouped_by_festival_day_then_stage_then_time(): void
    {
        $this->assertSame([
            ['2030-06-14', '18:00', '18:30', 30, 'Main Stage', '', 'Doors', '', ''],
            ['2030-06-14', '23:00', '00:30', 90, 'Main Stage', '', '', 'Nova', ''],
            ['2030-06-14', '19:00', '20:00', 60, 'Tent', '', 'Panel', 'Nova; Secret', ''],
            ['2030-06-15', '11:00', '12:00', 60, '', '', 'Yoga', '', ''],
        ], $this->export()->scheduleRows());
    }

    public function test_lineup_rows_include_hidden_people_and_missing_detail_flags(): void
    {
        $this->assertSame([
            ['Nova', 'HEADLINER', 'Closer', 'Techno', "Loud & late\nBerlin", "https://open.spotify.com/artist/x\nhttps://nova.example.com", 2, 'No', 'Yes', 'Yes'],
            ['Secret', 'STANDARD', '', '', '', '', 1, 'Yes', 'No', 'No'],
        ], $this->export()->lineupRows());
    }
}
