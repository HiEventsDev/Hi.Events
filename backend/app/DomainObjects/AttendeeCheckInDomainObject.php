<?php

namespace HiEvents\DomainObjects;

class AttendeeCheckInDomainObject extends Generated\AttendeeCheckInDomainObjectAbstract
{
    private ?AttendeeDomainObject $attendee = null;

    private ?CheckInListDomainObject $checkInList = null;

    public function getAttendee(): ?AttendeeDomainObject
    {
        return $this->attendee;
    }

    public function setAttendee(AttendeeDomainObject $attendee): self
    {
        $this->attendee = $attendee;
        return $this;
    }

    public function setCheckInList(?CheckInListDomainObject $checkInList): AttendeeCheckInDomainObject
    {
        $this->checkInList = $checkInList;
        return $this;
    }

    public function getCheckInList(): ?CheckInListDomainObject
    {
        return $this->checkInList;
    }
}
