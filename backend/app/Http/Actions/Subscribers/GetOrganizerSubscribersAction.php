<?php

namespace HiEvents\Http\Actions\Subscribers;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\EventSubscriberRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOrganizerSubscribersAction extends BaseAction
{
    public function __construct(
        private readonly EventSubscriberRepositoryInterface $subscriberRepository,
    )
    {
    }

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        $subscribers = $this->subscriberRepository->findByOrganizerId($organizerId, $page, $perPage);

        return $this->jsonResponse($subscribers);
    }
}
