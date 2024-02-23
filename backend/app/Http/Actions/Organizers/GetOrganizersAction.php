<?php

namespace TicketKitten\Http\Actions\Organizers;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\ImageDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\OrganizerRepositoryInterface;
use TicketKitten\Resources\Organizer\OrganizerResource;

class GetOrganizersAction extends BaseAction
{
    public function __construct(private readonly OrganizerRepositoryInterface $organizerRepository)
    {
    }

    public function __invoke(): JsonResponse
    {
        $organizers = $this->organizerRepository
            ->loadRelation(ImageDomainObject::class)
            ->findwhere([
                'account_id' => $this->getAuthenticatedUser()->getAccountId()
            ]);

        return $this->resourceResponse(
            resource: OrganizerResource::class,
            data: $organizers,
        );
    }
}
