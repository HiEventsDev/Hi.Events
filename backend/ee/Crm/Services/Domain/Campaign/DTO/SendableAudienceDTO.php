<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;

class SendableAudienceDTO extends BaseDataObject
{
    public function __construct(
        public readonly ?CompiledSegmentDTO $segment,
        public readonly int $mailable_count,
        public readonly array $sms_countries = [],
    ) {}
}
