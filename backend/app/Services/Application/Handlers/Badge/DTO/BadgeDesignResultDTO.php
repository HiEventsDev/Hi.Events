<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Badge\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Services\Domain\Badge\DTO\BadgeDesignDTO;

class BadgeDesignResultDTO extends BaseDataObject
{
    public function __construct(
        public BadgeDesignDTO $design,
        public bool $is_customized,
    ) {}
}
