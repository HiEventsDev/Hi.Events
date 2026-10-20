<?php

namespace HiEvents\DomainObjects\Interfaces;

use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;

interface IsSortable
{
    /**
     * The default sort column
     */
    public static function getDefaultSort(): string;

    /**
     * The default sort order - asc or desc
     */
    public static function getDefaultSortDirection(): string;

    public static function getAllowedSorts(): AllowedSorts;
}
