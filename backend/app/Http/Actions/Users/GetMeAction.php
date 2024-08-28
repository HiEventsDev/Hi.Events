<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\Http\Actions\Auth\BaseAuthAction;
use HiEvents\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;

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
