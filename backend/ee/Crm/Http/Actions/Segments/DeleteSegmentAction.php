<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Segments;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DeleteSegmentHandler;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteSegmentAction extends BaseCrmAction
{
    public function __construct(
        private readonly DeleteSegmentHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $segmentId): Response|JsonResponse
    {
        $this->authorizeCrm($organizerId);

        try {
            $this->handler->handle($organizerId, $segmentId);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->deletedResponse();
    }
}
