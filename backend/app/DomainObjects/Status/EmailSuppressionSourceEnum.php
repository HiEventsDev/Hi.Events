<?php

namespace HiEvents\DomainObjects\Status;

enum EmailSuppressionSourceEnum: string
{
    case SES_NOTIFICATION = 'ses_notification';
    case MANUAL = 'manual';
}
