<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Resources\BoxOfficeCatalogueResourcePublic;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\GetBoxOfficeProductsPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetBoxOfficeProductsPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetBoxOfficeProductsPublicHandler $handler,
    ) {}

    public function __invoke(Request $request, string $boxOfficeShortId): JsonResponse
    {
        /** @var BoxOfficeSessionDTO $session */
        $session = $request->attributes->get(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE);

        return $this->resourceResponse(
            resource: BoxOfficeCatalogueResourcePublic::class,
            data: $this->handler->handle(
                boxOffice: $request->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE),
                occurrenceId: $session->event_occurrence_id,
            ),
        );
    }
}
