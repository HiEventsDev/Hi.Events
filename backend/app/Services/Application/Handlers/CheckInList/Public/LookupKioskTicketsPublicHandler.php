<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Application\Handlers\CheckInList\Public\DTO\LookupKioskTicketsPublicDTO;
use HiEvents\Services\Domain\CheckInList\CheckInListActivityValidator;
use Illuminate\Support\Collection;

class LookupKioskTicketsPublicHandler
{
    public function __construct(
        private readonly CheckInListRepositoryInterface $checkInListRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly CheckInListActivityValidator $checkInListActivityValidator,
    ) {}

    /**
     * @return Collection<int, AttendeeDomainObject>
     *
     * @throws ResourceNotFoundException
     * @throws CannotCheckInException
     */
    public function handle(LookupKioskTicketsPublicDTO $dto): Collection
    {
        /** @var CheckInListDomainObject|null $checkInList */
        $checkInList = $this->checkInListRepository
            ->loadRelation(ProductDomainObject::class)
            ->findFirstWhere([CheckInListDomainObjectAbstract::SHORT_ID => $dto->checkInListShortId]);

        if ($checkInList === null || ! $checkInList->getKioskMode() || ! $checkInList->getKioskLookupEnabled()) {
            throw new ResourceNotFoundException(__('Check-in list not found'));
        }

        $this->checkInListActivityValidator->assertActive($checkInList);

        return $this->attendeeRepository->findKioskTicketMatches(
            eventId: $checkInList->getEventId(),
            email: $dto->email,
            lastName: $dto->lastName,
            productIds: $checkInList->getProducts()?->map(fn (ProductDomainObject $product) => $product->getId())->all() ?? [],
            eventOccurrenceId: $checkInList->getEventOccurrenceId(),
        );
    }
}
