<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\EndBoxOfficeSessionPublicHandler;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EndBoxOfficeSessionPublicAction extends BaseAction
{
    public function __construct(
        private readonly EndBoxOfficeSessionPublicHandler $handler,
    ) {}

    public function __invoke(Request $request, string $boxOfficeShortId): Response
    {
        $this->handler->handle($request->header(AuthenticateBoxOfficeSession::SESSION_HEADER));

        return $this->deletedResponse();
    }
}
