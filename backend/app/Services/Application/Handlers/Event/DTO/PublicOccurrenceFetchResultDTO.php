<?php

namespace HiEvents\Services\Application\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use Illuminate\Support\Collection;

class PublicOccurrenceFetchResultDTO extends BaseDataObject
{
    public function __construct(
        public readonly Collection $occurrences,
        public readonly bool $truncated,
    ) {}
}
