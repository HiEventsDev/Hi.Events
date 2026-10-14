<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailTemplateDTO;

class AdminLifecycleEmailDTO extends BaseDataObject
{
    public function __construct(
        public LifecycleEmailTemplateDTO $template,
        public int $sentTotal,
        public int $sentLastThirtyDays,
        public int $delivered,
        public int $bounced,
        public int $opened,
        public int $clicked,
        public array $tokens,
    ) {}
}
