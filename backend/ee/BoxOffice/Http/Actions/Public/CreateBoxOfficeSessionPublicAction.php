<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\Enterprise\BoxOffice\Exceptions\CannotSellException;
use HiEvents\Enterprise\BoxOffice\Exceptions\TooManyPinAttemptsException;
use HiEvents\Enterprise\BoxOffice\Http\Request\CreateBoxOfficeSessionPublicRequest;
use HiEvents\Enterprise\BoxOffice\Resources\BoxOfficeSessionResourcePublic;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\CreateBoxOfficeSessionPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\CreateBoxOfficeSessionDTO;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CreateBoxOfficeSessionPublicAction extends BaseAction
{
    public function __construct(
        private readonly CreateBoxOfficeSessionPublicHandler $handler,
    ) {}

    public function __invoke(CreateBoxOfficeSessionPublicRequest $request, string $boxOfficeShortId): JsonResponse
    {
        try {
            $session = $this->handler->handle(new CreateBoxOfficeSessionDTO(
                box_office_short_id: $boxOfficeShortId,
                operator_name: strip_tags(trim($request->validated('operator_name'))),
                pin: $request->validated('pin'),
                event_occurrence_id: $request->validated('event_occurrence_id'),
                stripe_terminal_reader_id: $request->validated('stripe_terminal_reader_id'),
                ip_address: $request->ip(),
                authenticated_user: $this->isUserAuthenticated() ? $this->getAuthenticatedUser() : null,
                authenticated_account_id: $this->isUserAuthenticated() ? $this->getAuthenticatedAccountId() : null,
            ));
        } catch (CannotSellException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        } catch (UnauthorizedException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (TooManyPinAttemptsException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_TOO_MANY_REQUESTS);
        }

        return $this->resourceResponse(
            resource: BoxOfficeSessionResourcePublic::class,
            data: $session,
            statusCode: Response::HTTP_CREATED,
        );
    }
}
