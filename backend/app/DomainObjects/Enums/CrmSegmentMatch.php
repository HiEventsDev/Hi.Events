<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmSegmentMatch: string
{
    use BaseEnum;

    case ALL = 'all';
    case ANY = 'any';
}
