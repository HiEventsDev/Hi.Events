<?php

namespace HiEvents\DomainObjects\Enums;

enum AnonymizationStrategy
{
    use BaseEnum;

    case NULLIFY;
    case SCRUB_TEXT;
    case SCRUB_EMAIL;
    case SCRUB_EMAIL_UNIQUE;
    case RANDOM_TOKEN;
}
