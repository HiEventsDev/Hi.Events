<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Suppressions;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\LookupSuppressionsRequest;
use HiEvents\Enterprise\Crm\Resources\SuppressionLookupResultResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\LookupSuppressionsHandler;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use Illuminate\Http\JsonResponse;

#[AllowedForReadOnlyUsers]
class LookupSuppressionsAction extends BaseCrmAction
{
    public function __construct(
        private readonly LookupSuppressionsHandler $handler,
    ) {}

    public function __invoke(LookupSuppressionsRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        $results = $this->handler->handle($organizerId, array_map('strval', $request->validated('emails')));

        return $this->resourceResponse(SuppressionLookupResultResource::class, collect($results));
    }
}
