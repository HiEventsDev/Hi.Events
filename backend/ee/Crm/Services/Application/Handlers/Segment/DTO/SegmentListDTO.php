<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use Illuminate\Support\Collection;

class SegmentListDTO extends BaseDataObject
{
    /**
     * @param  PresetSegmentDTO[]  $presets
     */
    public function __construct(
        public readonly array $presets,
        public readonly Collection $segments,
    ) {}
}
