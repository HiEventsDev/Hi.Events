<?php

namespace HiEvents\Services\Domain\Attendee;

use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Domain\Attendee\DTO\AttendeeCredentialsDTO;

class AttendeeCredentialRotationService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    ) {}

    public function issueFor(int $eventId): AttendeeCredentialsDTO
    {
        do {
            $publicId = IdHelper::publicId(IdHelper::ATTENDEE_PREFIX);
        } while ($this->isPublicIdTaken($eventId, $publicId));

        return new AttendeeCredentialsDTO(
            short_id: IdHelper::shortId(IdHelper::ATTENDEE_PREFIX),
            public_id: $publicId,
        );
    }

    private function isPublicIdTaken(int $eventId, string $publicId): bool
    {
        return $this->attendeeRepository->countWhere([
            AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
            AttendeeDomainObjectAbstract::PUBLIC_ID => $publicId,
        ]) > 0;
    }
}
