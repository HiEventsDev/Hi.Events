<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Lineup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use Illuminate\Support\Collection;

class LineupHighlightSelectionDTO extends BaseDataObject
{
    /**
     * @param  Collection<int, EventLineupEntryDomainObject>  $entries
     * @param  Collection<int, array{item: EventScheduleItemDomainObject, people: Collection<int, EventLineupEntryDomainObject>}>  $slots
     */
    public function __construct(
        public readonly Collection $entries,
        public readonly Collection $slots,
    ) {}
}
