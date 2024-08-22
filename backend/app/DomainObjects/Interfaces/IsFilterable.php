<?php

namespace HiEvents\DomainObjects\Interfaces;

interface IsFilterable
{
    /**
     * @return array<string>
     */
    public static function getAllowedFilterFields(): array;
}
