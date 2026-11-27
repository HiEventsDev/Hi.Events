<?php

namespace HiEvents\Services\Domain\Event\ShareSuggestion\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\ShareCaptionTone;

class ShareCaptionDTO extends BaseDataObject
{
    public function __construct(
        public readonly ShareCaptionTone $tone,
        public readonly string $text,
    ) {}
}
