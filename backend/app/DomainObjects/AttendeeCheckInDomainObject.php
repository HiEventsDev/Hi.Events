<?php

namespace HiEvents\DomainObjects;

class AttendeeCheckInDomainObject extends Generated\AttendeeCheckInDomainObjectAbstract
{
    private ?AttendeeDomainObject $attendee = null;

    public function getAttendee(): ?AttendeeDomainObject
    {
        return $this->attendee;
    }

    public function setAttendee(AttendeeDomainObject $attendee): self
    {
        $this->attendee = $attendee;
        return $this;
    }
}
