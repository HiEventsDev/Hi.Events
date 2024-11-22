<?php

namespace HiEvents\Http\Actions\Products;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Product\SortProductsRequest;
use HiEvents\Services\Application\Handlers\Product\SortProductsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SortProductsAction extends BaseAction
{
    public function __construct(
        private readonly SortProductsHandler $sortProductsHandler
    )
    {
    }

    public function __invoke(SortProductsRequest $request, int $eventId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->sortProductsHandler->handle(
                $eventId,
                $request->validated('sorted_categories'),
            );
        } catch (ResourceConflictException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->noContentResponse();
    }

}
