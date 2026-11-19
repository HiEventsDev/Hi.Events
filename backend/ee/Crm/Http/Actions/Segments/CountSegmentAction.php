<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Segments;

use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\CountSegmentHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountSegmentAction extends BaseCrmAction
{
    public function __construct(
        private readonly CountSegmentHandler $handler,
    ) {}

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        try {
            $count = $this->handler->handle($organizerId, $this->segmentReference($request), $this->canViewFinancials());
        } catch (InvalidSegmentDefinitionException $exception) {
            throw $this->invalidSegment($exception);
        }

        return $this->jsonResponse($count, wrapInData: true);
    }
}
