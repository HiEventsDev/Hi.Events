<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmSegmentOperator: string
{
    case GTE = 'gte';
    case LTE = 'lte';
    case EQ = 'eq';
    case WITHIN_DAYS = 'within_days';
    case NOT_WITHIN_DAYS = 'not_within_days';
    case BEFORE = 'before';
    case AFTER = 'after';
    case IS_EMPTY = 'is_empty';
    case IN = 'in';
    case NOT_IN = 'not_in';
    case IS_TRUE = 'is_true';
    case IS_FALSE = 'is_false';
}
