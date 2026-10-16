<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Lineup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EmailLineupHighlightsDTO extends BaseDataObject
{
    /**
     * @param  string[]  $names
     * @param  array<int, array{time: string, title: string, stage: ?string}>  $slots
     */
    public function __construct(
        public readonly string $heading,
        public readonly array $names,
        public readonly array $slots,
        public readonly string $url,
    ) {}
}
