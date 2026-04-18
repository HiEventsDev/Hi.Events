<?php

namespace HiEvents\Services\Application\Handlers\Contact\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ContactLookupResultDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $found,
        public readonly ?string $first_name = null,
        public readonly ?string $last_name = null,
        public readonly array $question_answers = [],
    ) {}
}
