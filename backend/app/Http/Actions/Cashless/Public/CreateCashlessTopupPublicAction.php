<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Public;

use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Cashless\CreateCashlessTopupRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Services\Application\Handlers\Cashless\CreateCashlessTopupHandler;
use HiEvents\Services\Application\Handlers\Cashless\DTO\CreateCashlessTopupDTO;
use HiEvents\Services\Application\Locale\LocaleService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Http\JsonResponse;
use Throwable;

class CreateCashlessTopupPublicAction extends BaseAction
{
    public function __construct(
        private readonly CreateCashlessTopupHandler $createCashlessTopupHandler,
        private readonly CheckoutSessionManagementService $sessionIdentifierService,
        private readonly LocaleService $localeService,
    ) {}

    /**
     * Start a cashless top-up
     *
     * Creates a regular order containing the event's cashless credit product, so the top-up is
     * paid for through the same checkout as a ticket purchase. The wallet is credited once the
     * order completes.
     *
     * @throws Throwable
     */
    public function __invoke(
        CreateCashlessTopupRequest $request,
        int $eventId,
        string $ticketReference,
    ): JsonResponse {
        $sessionId = $this->sessionIdentifierService->getSessionId();

        try {
            $order = $this->createCashlessTopupHandler->handle(CreateCashlessTopupDTO::from([
                'event_id' => $eventId,
                'ticket_reference' => $ticketReference,
                'amount' => (float) $request->input('amount'),
                'session_identifier' => $sessionId,
                'is_user_authenticated' => $this->isUserAuthenticated(),
                'order_locale' => $this->localeService->getLocaleOrDefault($request->getPreferredLanguage()),
            ]));
        } catch (CashlessNotEnabledException|CashlessWalletUnavailableException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_NOT_FOUND,
            );
        }

        $order->setSessionIdentifier($sessionId);

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
            statusCode: ResponseCodes::HTTP_CREATED,
        )->withCookie(
            cookie: $this->sessionIdentifierService->getSessionCookie(),
        );
    }
}
