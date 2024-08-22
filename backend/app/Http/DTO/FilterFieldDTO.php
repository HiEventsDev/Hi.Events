<?php

namespace HiEvents\Http\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class FilterFieldDTO extends BaseDTO
{
    public function __construct(
        public readonly ?string $field = null,
        public readonly ?string $operator = null,
        public readonly ?string $value = null,
    )
    {
    }
}
