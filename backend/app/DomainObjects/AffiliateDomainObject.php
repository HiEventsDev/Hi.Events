<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;

class AffiliateDomainObject extends Generated\AffiliateDomainObjectAbstract implements IsSortable
{
    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::CREATED_AT => [
                    'asc' => __('Oldest First'),
                    'desc' => __('Newest First'),
                ],
                self::NAME => [
                    'asc' => __('Name A-Z'),
                    'desc' => __('Name Z-A'),
                ],
                self::TOTAL_SALES => [
                    'asc' => __('Sales Ascending'),
                    'desc' => __('Sales Descending'),
                ],
                self::TOTAL_SALES_GROSS => [
                    'asc' => __('Revenue Ascending'),
                    'desc' => __('Revenue Descending'),
                ],
            ],
        );
    }

    public static function getDefaultSort(): string
    {
        return self::CREATED_AT;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'desc';
    }
}
