<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class GetApiKeysAction extends BaseAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        $tokens = $request->user()->tokens;
        return $this->jsonResponse($tokens);
    }
}