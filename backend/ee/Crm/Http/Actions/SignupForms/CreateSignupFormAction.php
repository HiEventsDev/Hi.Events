<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\SignupForms;

use HiEvents\Enterprise\Crm\Http\Request\UpsertSignupFormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CreateSignupFormAction extends BaseUpsertSignupFormAction
{
    public function __invoke(UpsertSignupFormRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        return $this->upsert($request, $organizerId, null, Response::HTTP_CREATED);
    }
}
