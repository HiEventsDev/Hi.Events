<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Public;

use HiEvents\Enterprise\Crm\Resources\SignupFormPublicResource;
use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SignupFormPublicLookup;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetOrganizerSignupFormPublicAction extends BaseAction
{
    public function __construct(
        private readonly SignupFormPublicLookup $lookup,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $form = $this->lookup->findForOrganizerPage($organizerId);

        if ($form === null) {
            return $this->jsonResponse(null, wrapInData: true);
        }

        return $this->resourceResponse(SignupFormPublicResource::class, $form);
    }
}
