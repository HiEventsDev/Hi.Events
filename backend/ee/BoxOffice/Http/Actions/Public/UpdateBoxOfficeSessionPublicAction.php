<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Http\Request\UpdateBoxOfficeSessionPublicRequest;
use HiEvents\Enterprise\BoxOffice\Resources\BoxOfficeSessionResourcePublic;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\UpdateBoxOfficeSessionDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\UpdateBoxOfficeSessionPublicHandler;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class UpdateBoxOfficeSessionPublicAction extends BaseAction
{
    public function __construct(
        private readonly UpdateBoxOfficeSessionPublicHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(UpdateBoxOfficeSessionPublicRequest $request, string $boxOfficeShortId): JsonResponse
    {
        $session = $this->handler->handle(new UpdateBoxOfficeSessionDTO(
            box_office_short_id: $boxOfficeShortId,
            token: (string) $request->header(AuthenticateBoxOfficeSession::SESSION_HEADER),
            session: $request->attributes->get(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE),
            event_occurrence_id: $request->validated('event_occurrence_id'),
            change_reader: $request->has('stripe_terminal_reader_id'),
            stripe_terminal_reader_id: $request->validated('stripe_terminal_reader_id'),
        ));

        return $this->resourceResponse(
            resource: BoxOfficeSessionResourcePublic::class,
            data: $session,
        );
    }
}
