<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\AttendeeDomainObject;

class CashlessAttendeeNameMasker
{
    public function maskLastName(?AttendeeDomainObject $attendee): void
    {
        $lastName = $attendee?->getLastName();

        $attendee?->setLastName(
            $lastName === null || $lastName === '' ? '' : mb_strtoupper(mb_substr($lastName, 0, 1)).'.'
        );
    }
}
