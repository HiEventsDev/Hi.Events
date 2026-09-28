<?php

namespace Tests\Unit\Services\Domain\Cashless;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Services\Domain\Cashless\CashlessAttendeeNameMasker;
use Tests\TestCase;

class CashlessAttendeeNameMaskerTest extends TestCase
{
    public function test_only_the_initial_of_the_last_name_is_kept(): void
    {
        $attendee = (new AttendeeDomainObject)->setFirstName('Marie')->setLastName('Durand');

        (new CashlessAttendeeNameMasker)->maskLastName($attendee);

        $this->assertSame('Marie', $attendee->getFirstName());
        $this->assertSame('D.', $attendee->getLastName());
    }

    public function test_the_initial_is_uppercased_and_multibyte_safe(): void
    {
        $attendee = (new AttendeeDomainObject)->setLastName('élodie');

        (new CashlessAttendeeNameMasker)->maskLastName($attendee);

        $this->assertSame('É.', $attendee->getLastName());
    }

    public function test_an_empty_last_name_stays_empty(): void
    {
        $attendee = (new AttendeeDomainObject)->setLastName('');

        (new CashlessAttendeeNameMasker)->maskLastName($attendee);

        $this->assertSame('', $attendee->getLastName());
    }

    public function test_a_missing_attendee_is_ignored(): void
    {
        (new CashlessAttendeeNameMasker)->maskLastName(null);

        $this->addToAssertionCount(1);
    }
}
