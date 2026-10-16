<?php

declare(strict_types=1);

namespace HiEvents\Exports\Lineup;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\Services\Application\Handlers\Lineup\DTO\EventLineupDTO;
use HiEvents\Services\Domain\Lineup\ScheduleDay;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LineupExport implements Export, WithMultipleSheets
{
    private EventLineupDTO $lineup;

    private string $timezone;

    public function withData(EventLineupDTO $lineup, string $timezone): self
    {
        $this->lineup = $lineup;
        $this->timezone = $timezone;

        return $this;
    }

    public function sheets(): array
    {
        return [
            new LineupExportSheet(__('Schedule'), $this->scheduleHeadings(), $this->scheduleRows()),
            new LineupExportSheet(__('Lineup'), $this->lineupHeadings(), $this->lineupRows()),
        ];
    }

    public function scheduleHeadings(): array
    {
        return [
            __('Date'),
            __('Start'),
            __('End'),
            __('Duration (min)'),
            __('Stage'),
            __('Venue'),
            __('Title'),
            __('People'),
            __('Description'),
        ];
    }

    public function lineupHeadings(): array
    {
        return [
            __('Name'),
            __('Billing'),
            __('Role'),
            __('Tagline'),
            __('Bio'),
            __('Links'),
            __('Time slots'),
            __('Hidden'),
            __('Has photo'),
            __('Has bio'),
        ];
    }

    public function scheduleRows(): array
    {
        $entriesById = $this->lineup->entries->keyBy(fn (EventLineupEntryDomainObject $entry) => $entry->getId());
        $spaces = $this->lineup->spaces->values();
        $spaceOrder = $spaces->mapWithKeys(fn (EventSpaceDomainObject $space, int $index) => [$space->getId() => $index]);
        $spacesById = $spaces->keyBy(fn (EventSpaceDomainObject $space) => $space->getId());

        return $this->lineup->schedule_items
            ->map(function (EventScheduleItemDomainObject $item) {
                $start = $this->local($item->getStartsAt());

                return [
                    'item' => $item,
                    'start' => $start,
                    'end' => $this->local($item->getEndsAt()),
                    'day' => ScheduleDay::key($start),
                ];
            })
            ->sortBy([
                ['day', 'asc'],
                fn (array $a, array $b) => $spaceOrder->get($a['item']->getEventSpaceId() ?? '', PHP_INT_MAX)
                    <=> $spaceOrder->get($b['item']->getEventSpaceId() ?? '', PHP_INT_MAX),
                fn (array $a, array $b) => $a['start'] <=> $b['start'],
            ])
            ->map(function (array $row) use ($entriesById, $spacesById) {
                /** @var EventScheduleItemDomainObject $item */
                $item = $row['item'];
                /** @var EventSpaceDomainObject|null $space */
                $space = $spacesById->get($item->getEventSpaceId() ?? '');
                $people = ($item->getPerformers() ?? collect())
                    ->map(fn (EventScheduleItemPerformerDomainObject $performer) => $entriesById->get($performer->getEventLineupEntryId())?->getProfile()?->getName())
                    ->filter()
                    ->implode('; ');

                return [
                    $row['start']->toDateString(),
                    $row['start']->format('H:i'),
                    $row['end']->format('H:i'),
                    (int) $row['start']->diffInMinutes($row['end']),
                    $space?->getName() ?? '',
                    $space?->getEventLocation()?->getLocation()?->getName() ?? '',
                    $item->getTitle() ?? '',
                    $people,
                    $this->plainText($item->getDescription()),
                ];
            })
            ->values()
            ->all();
    }

    public function lineupRows(): array
    {
        $slotCounts = $this->slotCounts();

        return $this->lineup->entries
            ->map(function (EventLineupEntryDomainObject $entry) use ($slotCounts) {
                $profile = $entry->getProfile();
                $links = collect($profile?->getLinks() ?? [])->pluck('url')->implode("\n");
                $bio = $this->plainText($profile?->getBio());

                return [
                    $profile?->getName() ?? '',
                    $entry->getBillingTier(),
                    $entry->getRoleLabel() ?? '',
                    $profile?->getTagline() ?? '',
                    $bio,
                    $links,
                    $slotCounts->get($entry->getId(), 0),
                    $entry->getIsHidden() ? __('Yes') : __('No'),
                    ($profile?->getImages() ?? collect())->isNotEmpty() ? __('Yes') : __('No'),
                    $bio !== '' ? __('Yes') : __('No'),
                ];
            })
            ->values()
            ->all();
    }

    private function slotCounts(): Collection
    {
        return $this->lineup->schedule_items
            ->flatMap(fn (EventScheduleItemDomainObject $item) => ($item->getPerformers() ?? collect())
                ->map(fn (EventScheduleItemPerformerDomainObject $performer) => $performer->getEventLineupEntryId()))
            ->countBy();
    }

    private function local(string $utc): CarbonImmutable
    {
        return CarbonImmutable::parse($utc, 'UTC')->setTimezone($this->timezone);
    }

    private function plainText(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $text = preg_replace(['/<br\s*\/?>/i', '/<\/(p|div|li|h[1-6])>/i'], "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace("/[ \t]+\n/", "\n", $text)));
    }
}
