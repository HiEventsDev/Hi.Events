<?php

namespace HiEvents\DataTransferObjects;

class AttributesDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly mixed  $value,
        public readonly bool   $is_public = false,
    )
    {
    }
}
