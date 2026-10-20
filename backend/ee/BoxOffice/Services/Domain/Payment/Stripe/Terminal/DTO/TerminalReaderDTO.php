<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TerminalReaderDTO extends BaseDataObject
{
    public function __construct(
        public int $id,
        public string $label,
        public ?string $device_type,
        public string $status,
        public bool $is_available,
    ) {}
}
