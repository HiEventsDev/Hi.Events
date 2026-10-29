<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Lineup;

use HiEvents\DomainObjects\Enums\LineupBillingTier;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\Services\Domain\Lineup\DTO\LineupHighlightSelectionDTO;
use Illuminate\Support\Collection;

class LineupHighlightSelector
{
    private const MAX_NAMES = 6;

    private const MAX_SLOTS = 5;

    /**
     * @param  Collection<int, EventLineupEntryDomainObject>  $entries
     * @param  Collection<int, EventScheduleItemDomainObject>  $items
     */
    public function select(Collection $entries, Collection $items): LineupHighlightSelectionDTO
    {
        $highlighted = $this->highlightedEntries($entries);
        $slots = $this->slotRows($items, $entries, $highlighted);
        $namedInSlots = $slots
            ->filter(fn (array $slot) => $slot['item']->getTitle() === null)
            ->reduce(fn (Collection $named, array $slot) => $named->union($slot['people']), collect());

        return new LineupHighlightSelectionDTO(
            entries: $highlighted->diffKeys($namedInSlots)->take(self::MAX_NAMES),
            slots: $slots,
        );
    }

    private function highlightedEntries(Collection $entries): Collection
    {
        foreach ([LineupBillingTier::HEADLINER, LineupBillingTier::FEATURED] as $tier) {
            $matches = $entries->filter(fn (EventLineupEntryDomainObject $entry) => $entry->getBillingTier() === $tier->value);

            if ($matches->isNotEmpty()) {
                return $matches;
            }
        }

        return $entries;
    }

    private function slotRows(Collection $items, Collection $entries, Collection $highlighted): Collection
    {
        $entryKeysById = $entries->map(fn (EventLineupEntryDomainObject $entry) => $entry->getId())->flip();

        $rows = $items
            ->map(fn (EventScheduleItemDomainObject $item) => [
                'item' => $item,
                'people' => collect($item->getPerformers() ?? [])
                    ->map(fn (EventScheduleItemPerformerDomainObject $performer) => $entryKeysById->get($performer->getEventLineupEntryId()))
                    ->reject(fn (?int $entryKey) => $entryKey === null)
                    ->mapWithKeys(fn (int $entryKey) => [$entryKey => $entries->get($entryKey)]),
            ])
            ->filter(fn (array $row) => $row['item']->getTitle() !== null || $row['people']->isNotEmpty());

        $featured = $rows->filter(fn (array $row) => $row['people']->intersectByKeys($highlighted)->isNotEmpty());

        return ($featured->isNotEmpty() ? $featured : $rows)->take(self::MAX_SLOTS);
    }
}
