<?php

namespace HiEvents\DomainObjects\Enums;

enum ReportTypes: string
{
    use BaseEnum;

    case PRODUCT_SALES = 'product_sales';
    case DAILY_SALES_REPORT = 'daily_sales_report';
}
