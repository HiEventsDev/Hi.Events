<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EventAudienceDTO extends BaseDataObject
{
    /**
     * @param  array<int, array<string, mixed>>  $top_contacts
     * @param  array<int, array{event_id: int, title: string, shared_contacts: int}>  $overlaps
     */
    public function __construct(
        public readonly int $event_id,
        public readonly int $contacts_count,
        public readonly int $tickets_count,
        public readonly int $mailable_count,
        public readonly int $checked_in_count,
        public readonly int $new_count,
        public readonly int $returning_count,
        public readonly ?float $baseline_show_rate,
        public readonly ?int $expected_tickets,
        public readonly int $likely_no_show_contacts,
        public readonly array $top_contacts,
        public readonly array $overlaps,
    ) {}
}
