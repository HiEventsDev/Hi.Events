<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\EditAdminAttendeeDTO;

class EditAdminAttendeeHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    )
    {
    }

    public function handle(EditAdminAttendeeDTO $dto): AttendeeDomainObject
    {
        $this->attendeeRepository->updateWhere(
            attributes: array_filter([
                'first_name' => $dto->firstName,
                'last_name' => $dto->lastName,
                'email' => $dto->email,
                'notes' => $dto->notes,
            ], fn($value) => $value !== null),
            where: ['id' => $dto->attendeeId],
        );

        return $this->attendeeRepository->findById($dto->attendeeId);
    }
}
