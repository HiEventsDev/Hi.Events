<?php

namespace HiEvents\Http\Actions\Auth;

use App\Models\Sanctum\PersonalAccessToken;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use \DateTime;

class CreateApiKeyAction extends BaseAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        $abilities = ['*'];
        $expiryDateTime = null;
        if ($request->abilities && count($request->abilities) > 0) {
            $abilities = $request->abilities;
        }
        if ($request->expires_at) {
            $expiryDateTime = DateTime::createFromFormat("U", strtotime($request->expires_at));
        }
        return $this->jsonResponse($request->user()->createToken(
            $request->token_name,
            $abilities,
            $expiryDateTime));
    }
}