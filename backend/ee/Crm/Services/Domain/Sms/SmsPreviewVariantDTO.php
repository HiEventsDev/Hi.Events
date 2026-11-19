<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DataTransferObjects\BaseDataObject;

class SmsPreviewVariantDTO extends BaseDataObject
{
    /**
     * @param  string[]  $countries
     */
    public function __construct(
        public readonly array $countries,
        public readonly string $text,
        public readonly int $characters,
        public readonly int $parts,
        public readonly bool $is_unicode,
    ) {}
}
