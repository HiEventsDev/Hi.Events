<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertProfileDTO extends BaseDataObject
{
    /**
     * @param  string[]  $links
     */
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly string $name,
        public readonly ?string $tagline = null,
        public readonly ?string $bio = null,
        public readonly array $links = [],
        public readonly ?int $profile_id = null,
    ) {}
}
