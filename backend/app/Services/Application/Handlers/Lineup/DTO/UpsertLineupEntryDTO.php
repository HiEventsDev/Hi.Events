<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\LineupBillingTier;

class UpsertLineupEntryDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly LineupBillingTier $billing_tier,
        public readonly ?string $role_label = null,
        public readonly ?bool $is_hidden = null,
        public readonly ?int $profile_id = null,
        public readonly ?int $lineup_entry_id = null,
    ) {}
}
