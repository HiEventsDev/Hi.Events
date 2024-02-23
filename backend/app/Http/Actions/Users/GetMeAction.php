<?php

namespace TicketKitten\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use TicketKitten\Http\Actions\Auth\BaseAuthAction;
use TicketKitten\Resources\User\UserResource;

class GetMeAction extends BaseAuthAction
{
    public function __invoke(): JsonResponse
    {
        return $this->resourceResponse(UserResource::class, $this->getAuthenticatedUser());
    }
}
