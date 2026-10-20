<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;

class SeatMapDomainObject extends Generated\SeatMapDomainObjectAbstract implements IsSortable
{
    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::UPDATED_AT => [
                    'desc' => __('Recently Updated'),
                    'asc' => __('Least Recently Updated'),
                ],
                self::NAME => [
                    'asc' => __('Name A-Z'),
                    'desc' => __('Name Z-A'),
                ],
                self::SEAT_COUNT => [
                    'desc' => __('Most seats'),
                    'asc' => __('Fewest seats'),
                ],
            ]
        );
    }

    public static function getDefaultSort(): string
    {
        return self::UPDATED_AT;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'desc';
    }
}
