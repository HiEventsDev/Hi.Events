<?php

namespace HiEvents\Http\Actions\Federation;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Federation\ActivityPubTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetFederatedOrganizerAction extends BaseAction
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly ActivityPubTransformer       $transformer,
    )
    {
    }

    public function __invoke(int $organizerId, Request $request): JsonResponse
    {
        $organizer = $this->organizerRepository->findById($organizerId);
        $baseUrl = rtrim(config('app.url'), '/');
        $actor = $this->transformer->organizerToActor($organizer, $baseUrl);

        return response()->json($actor, 200, [
            'Content-Type' => 'application/activity+json',
        ]);
    }
}
