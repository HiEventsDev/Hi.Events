<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\Http\Actions\Auth\BaseAuthAction;
use HiEvents\Resources\User\MeResource;
use Illuminate\Http\JsonResponse;

class GetMeAction extends BaseAuthAction
{
    public function __invoke(): JsonResponse
    {
        return $this->resourceResponse(
            resource: MeResource::class,
            data: $this->getAuthenticatedUser(),
        );
    }
}
