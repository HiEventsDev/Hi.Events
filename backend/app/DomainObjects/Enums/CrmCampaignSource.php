<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmCampaignSource: string
{
    use BaseEnum;

    case AUTOMATION = 'automation';
}
