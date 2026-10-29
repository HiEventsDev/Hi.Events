<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BadgeRibbonDTO extends BaseDataObject
{
    public function __construct(
        public int $product_id,
        public ?string $label,
        public string $color,
    ) {}
}
