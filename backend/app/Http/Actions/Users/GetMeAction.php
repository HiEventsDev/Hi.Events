<?php

namespace HiEvents\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use HiEvents\Http\Actions\Auth\BaseAuthAction;
use HiEvents\Resources\User\UserResource;

class GetMeAction extends BaseAuthAction
{
    public function __invoke(): JsonResponse
    {
        return $this->resourceResponse(
            resource: UserResource::class,
            data: $this->getAuthenticatedUser(),
        );
    }
}
