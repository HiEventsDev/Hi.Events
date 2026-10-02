<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\GetBoxOfficeOrdersPublicHandler;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetBoxOfficeOrdersPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetBoxOfficeOrdersPublicHandler $handler,
    ) {}

    public function __invoke(Request $request, string $boxOfficeShortId): JsonResponse
    {
        return $this->filterableResourceResponse(
            resource: OrderResourcePublic::class,
            data: $this->handler->handle(
                boxOffice: $request->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE),
                params: $this->getPaginationQueryParams($request),
            ),
            domainObject: OrderDomainObject::class,
        );
    }
}
