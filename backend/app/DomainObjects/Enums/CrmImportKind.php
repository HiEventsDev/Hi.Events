<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmImportKind: string
{
    use BaseEnum;

    case CONTACTS = 'CONTACTS';
    case SUPPRESSIONS = 'SUPPRESSIONS';
}
