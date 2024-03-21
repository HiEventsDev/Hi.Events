<?php

namespace HiEvents\Http\Actions\Organizers;

use Illuminate\Http\JsonResponse;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Resources\Organizer\OrganizerResource;

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
                'account_id' => $this->getAuthenticatedAccountId(),
            ]);

        return $this->resourceResponse(
            resource: OrganizerResource::class,
            data: $organizers,
        );
    }
}
