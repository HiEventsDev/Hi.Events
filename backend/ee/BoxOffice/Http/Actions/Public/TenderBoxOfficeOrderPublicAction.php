<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\DomainObjects\Enums\BoxOfficeTender;
use HiEvents\Enterprise\BoxOffice\Exceptions\BoxOfficeSaleExpiredException;
use HiEvents\Enterprise\BoxOffice\Exceptions\CannotSellException;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Http\Request\TenderBoxOfficeOrderPublicRequest;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\TenderBoxOfficeOrderDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\TenderBoxOfficeOrderPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TenderBoxOfficeOrderPublicAction extends BaseAction
{
    public function __construct(
        private readonly TenderBoxOfficeOrderPublicHandler $handler,
    ) {}

    public function __invoke(
        TenderBoxOfficeOrderPublicRequest $request,
        string $boxOfficeShortId,
        string $orderShortId,
    ): JsonResponse {
        $reference = $request->validated('reference');
        /** @var BoxOfficeSessionDTO $session */
        $session = $request->attributes->get(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE);

        try {
            $order = $this->handler->handle(new TenderBoxOfficeOrderDTO(
                box_office: $request->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE),
                order_short_id: $orderShortId,
                tender: BoxOfficeTender::from($request->validated('tender')),
                amount_tendered: $request->validated('amount_tendered') !== null
                    ? (float) $request->validated('amount_tendered')
                    : null,
                reference: $reference === null ? null : strip_tags(trim($reference)),
                stripe_terminal_reader_id: $session->stripe_terminal_reader_id,
            ));
        } catch (BoxOfficeSaleExpiredException $exception) {
            return $this->jsonResponse([
                'message' => $exception->getMessage(),
                'error_code' => BoxOfficeSaleExpiredException::ERROR_CODE,
            ], Response::HTTP_CONFLICT);
        } catch (CannotSellException|ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
        );
    }
}
