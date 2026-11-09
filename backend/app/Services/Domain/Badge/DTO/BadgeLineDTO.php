<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\BadgeFieldSource;
use HiEvents\DomainObjects\Enums\BadgeTextSize;

class BadgeLineDTO extends BaseDataObject
{
    public function __construct(
        public BadgeFieldSource $source,
        public BadgeTextSize $size,
        public ?int $question_id = null,
        public ?string $text = null,
    ) {}
}
