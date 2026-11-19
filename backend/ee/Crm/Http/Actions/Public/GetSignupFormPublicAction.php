<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Public;

use HiEvents\Enterprise\Crm\Resources\SignupFormPublicResource;
use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SignupFormPublicLookup;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GetSignupFormPublicAction extends BaseAction
{
    public function __construct(
        private readonly SignupFormPublicLookup $lookup,
    ) {}

    public function __invoke(string $publicId): JsonResponse
    {
        $form = $this->lookup->findActive($publicId);

        if ($form === null) {
            return $this->errorResponse(__('This signup form isn\'t available.'), Response::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(SignupFormPublicResource::class, $form);
    }
}
