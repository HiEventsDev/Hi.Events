<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\Enterprise\BoxOffice\Resources\BoxOfficeResourcePublic;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\GetBoxOfficePublicHandler;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetBoxOfficePublicAction extends BaseAction
{
    public function __construct(
        private readonly GetBoxOfficePublicHandler $handler,
    ) {}

    public function __invoke(string $boxOfficeShortId): JsonResponse
    {
        return $this->resourceResponse(
            resource: BoxOfficeResourcePublic::class,
            data: $this->handler->handle(
                shortId: $boxOfficeShortId,
                user: $this->isUserAuthenticated() ? $this->getAuthenticatedUser() : null,
                accountId: $this->isUserAuthenticated() ? $this->getAuthenticatedAccountId() : null,
            ),
        );
    }
}
