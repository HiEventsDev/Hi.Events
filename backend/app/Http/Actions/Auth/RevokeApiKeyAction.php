<?php

namespace HiEvents\Http\Actions\Auth;

use App\Models\Sanctum\PersonalAccessToken;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RevokeApiKeyAction extends BaseAction
{
    public function __invoke(Request $request, int $apiKey): Response
    {
        $this->minimumAllowedRole(Role::ADMIN);

        if ($request->user()->tokens()->where('id', $apiKey)->delete()) {
            return $this->deletedResponse();
        } else {
            return $this->notFoundResponse();
        }
    }
}