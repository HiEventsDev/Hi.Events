<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Segments;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\SegmentResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\GetSegmentsHandler;
use Illuminate\Http\JsonResponse;

class GetSegmentsAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetSegmentsHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        $segments = $this->handler->handle($this->getAuthenticatedAccountId(), $organizerId);

        return $this->jsonResponse([
            'data' => SegmentResource::collection($segments->segments),
            'presets' => $segments->presets,
        ]);
    }
}
