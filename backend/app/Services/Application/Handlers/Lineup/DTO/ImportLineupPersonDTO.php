<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\LineupBillingTier;

class ImportLineupPersonDTO extends BaseDataObject
{
    /**
     * @param  string[]  $links
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $tagline = null,
        public readonly ?string $bio = null,
        public readonly array $links = [],
        public readonly ?LineupBillingTier $billing_tier = null,
        public readonly ?string $role_label = null,
        public readonly ?bool $is_hidden = null,
    ) {}
}
