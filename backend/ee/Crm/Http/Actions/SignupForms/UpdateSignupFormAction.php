<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\SignupForms;

use HiEvents\Enterprise\Crm\Http\Request\UpsertSignupFormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UpdateSignupFormAction extends BaseUpsertSignupFormAction
{
    public function __invoke(UpsertSignupFormRequest $request, int $organizerId, int $formId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        return $this->upsert($request, $organizerId, $formId, Response::HTTP_OK);
    }
}
