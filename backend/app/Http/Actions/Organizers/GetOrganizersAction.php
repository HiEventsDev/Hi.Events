<?php

namespace HiEvents\Http\Actions\Organizers;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Resources\Organizer\OrganizerResource;
use Illuminate\Http\JsonResponse;

class GetOrganizersAction extends BaseAction
{
    public function __construct(private readonly OrganizerRepositoryInterface $organizerRepository) {}

    public function __invoke(): JsonResponse
    {
        $access = $this->getAuthenticatedAccess();

        $where = ['account_id' => $this->getAuthenticatedAccountId()];

        if ($access->isRestricted()) {
            $where[] = ['id', 'in', $access->readableOrganizerIds()];
        }

        $organizers = $this->organizerRepository
            ->loadRelation(ImageDomainObject::class)
            ->findwhere($where);

        return $this->resourceResponse(
            resource: OrganizerResource::class,
            data: $organizers,
        );
    }
}
