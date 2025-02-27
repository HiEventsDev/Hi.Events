<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Resources\Attendee\AttendeeResourcePublic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GetAttendeeActionPublic extends BaseAction
{
    private AttendeeRepositoryInterface $attendeeRepository;

    public function __construct(AttendeeRepositoryInterface $attendeeRepository)
    {
        $this->attendeeRepository = $attendeeRepository;
    }

    /**
     * @todo move to handler
     */
    public function __invoke(int $eventId, string $attendeeShortId): JsonResponse|Response
    {
        $attendee = $this->attendeeRepository
            ->loadRelation(new Relationship(
                domainObject: ProductDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: ProductPriceDomainObject::class,
                    ),
                ], name: 'product'))
            ->findFirstWhere([
                AttendeeDomainObjectAbstract::SHORT_ID => $attendeeShortId
            ]);

        if (!$attendee) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(AttendeeResourcePublic::class, $attendee);
    }
}
