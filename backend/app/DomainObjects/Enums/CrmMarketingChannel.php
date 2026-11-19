<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmMarketingChannel: string
{
    use BaseEnum;

    case EMAIL = 'EMAIL';
    case SMS = 'SMS';
}
