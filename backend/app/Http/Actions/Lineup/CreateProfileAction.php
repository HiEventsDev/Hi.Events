<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Lineup\UpsertProfileRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Lineup\ProfileResource;
use HiEvents\Services\Application\Handlers\Lineup\DTO\UpsertProfileDTO;
use HiEvents\Services\Application\Handlers\Lineup\UpsertProfileHandler;
use Illuminate\Http\JsonResponse;

class CreateProfileAction extends BaseAction
{
    public function __construct(
        private readonly UpsertProfileHandler $handler,
    ) {}

    public function __invoke(int $organizerId, UpsertProfileRequest $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $profile = $this->handler->handle(new UpsertProfileDTO(
            account_id: $this->getAuthenticatedAccountId(),
            organizer_id: $organizerId,
            name: $request->validated('name'),
            tagline: $request->validated('tagline'),
            bio: $request->validated('bio'),
            links: $request->validated('links') ?? [],
            profile_id: null,
        ));

        return $this->resourceResponse(
            resource: ProfileResource::class,
            data: $profile,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
