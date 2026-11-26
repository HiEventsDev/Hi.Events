<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class AutomationFirstSendDTO extends BaseDataObject
{
    public const NEXT_PUBLISH = 'NEXT_PUBLISH';

    public const AFTER_DATE = 'AFTER_DATE';

    public const DAILY = 'DAILY';

    public const BEFORE_DATE = 'BEFORE_DATE';

    public const AFTER_ABANDON = 'AFTER_ABANDON';

    public const ON_ENTRY = 'ON_ENTRY';

    public function __construct(
        public readonly string $kind,
        public readonly ?string $at,
        public readonly ?string $event_title,
        public readonly ?string $event_date,
    ) {}
}
