<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Resources\Auth\Sso\UserIdentityResource;
use HiEvents\Services\Application\Handlers\Auth\Sso\GetSsoIdentitiesHandler;
use Illuminate\Http\JsonResponse;

#[AllowedForReadOnlyUsers]
class GetSsoIdentitiesAction extends BaseAction
{
    public function __construct(
        private readonly GetSsoIdentitiesHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->resourceResponse(
            UserIdentityResource::class,
            $this->handler->handle($this->getAuthenticatedUser()->getId()),
        );
    }
}
