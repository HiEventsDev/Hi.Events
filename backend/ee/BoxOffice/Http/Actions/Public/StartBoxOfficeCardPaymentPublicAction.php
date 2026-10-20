<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\Enterprise\BoxOffice\Exceptions\BoxOfficeSaleExpiredException;
use HiEvents\Enterprise\BoxOffice\Exceptions\CannotSellException;
use HiEvents\Enterprise\BoxOffice\Exceptions\Stripe\TerminalReaderUnavailableException;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\StartBoxOfficeCardPaymentPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\Stripe\CreatePaymentIntentFailedException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StartBoxOfficeCardPaymentPublicAction extends BaseAction
{
    public function __construct(
        private readonly StartBoxOfficeCardPaymentPublicHandler $handler,
    ) {}

    public function __invoke(Request $request, string $boxOfficeShortId, string $orderShortId): JsonResponse
    {
        /** @var BoxOfficeSessionDTO $session */
        $session = $request->attributes->get(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE);

        try {
            $order = $this->handler->handle(
                boxOffice: $request->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE),
                orderShortId: $orderShortId,
                readerId: $session->stripe_terminal_reader_id,
            );
        } catch (TerminalReaderUnavailableException $exception) {
            return $this->jsonResponse([
                'message' => $exception->getMessage(),
                'error_code' => TerminalReaderUnavailableException::ERROR_CODE,
            ], Response::HTTP_CONFLICT);
        } catch (BoxOfficeSaleExpiredException $exception) {
            return $this->jsonResponse([
                'message' => $exception->getMessage(),
                'error_code' => BoxOfficeSaleExpiredException::ERROR_CODE,
            ], Response::HTTP_CONFLICT);
        } catch (CannotSellException|ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        } catch (CreatePaymentIntentFailedException|StripeClientConfigurationException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
        );
    }
}
