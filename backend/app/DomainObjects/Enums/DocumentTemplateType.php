<?php

namespace HiEvents\DomainObjects\Enums;

enum DocumentTemplateType
{
    use BaseEnum;

    case CERTIFICATE;
    case RECEIPT;
    case BADGE;
    case CUSTOM;
}
