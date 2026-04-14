<?php

namespace HiEvents\DomainObjects\Status;

enum EmailSuppressionReasonEnum: string
{
    case BOUNCE = 'bounce';
    case COMPLAINT = 'complaint';
}
