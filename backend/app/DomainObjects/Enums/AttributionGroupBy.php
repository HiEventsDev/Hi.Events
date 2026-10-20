<?php

namespace HiEvents\DomainObjects\Enums;

enum AttributionGroupBy: string
{
    use BaseEnum;

    case SOURCE = 'source';
    case MEDIUM = 'medium';
    case CAMPAIGN = 'campaign';
    case CONTENT = 'content';
    case TERM = 'term';
    case CTA = 'cta';
    case SOURCE_TYPE = 'source_type';
}
