<?php

namespace TicketKitten\DomainObjects\Interfaces;

interface IsFilterable
{
    /**
     * @return array<string, string>
     */
    public static function getAllowedFilterFields(): array;
}
