<?php

namespace HiEvents\Services\Domain\Mail\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EmailAccentColorDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $buttonColor,
        public readonly string $buttonTextColor,
        public readonly ?string $linkColor,
    ) {}
}
