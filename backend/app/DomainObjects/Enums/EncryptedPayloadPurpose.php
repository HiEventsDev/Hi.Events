<?php

namespace HiEvents\DomainObjects\Enums;

enum EncryptedPayloadPurpose: string
{
    use BaseEnum;

    case EMAIL_CONFIRMATION = 'EMAIL_CONFIRMATION';
    case EMAIL_CHANGE = 'EMAIL_CHANGE';
    case INVITATION = 'INVITATION';
}
