<?php

namespace HiEvents\DomainObjects\Enums;

enum SalesReportFrequency
{
    use BaseEnum;

    case DAILY;
    case WEEKLY;
    case MONTHLY;
}
