<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum VatValidationStatus: string
{
    use BaseEnum;

    case PENDING = 'PENDING';
    case VALIDATING = 'VALIDATING';
    case VALID = 'VALID';
    case INVALID = 'INVALID';
    case FAILED = 'FAILED';
}
