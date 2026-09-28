<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Public;

use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\InsufficientCashlessBalanceException;
use HiEvents\Http\Request\Cashless\CreateCashlessPurchaseRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessTransactionResourcePublic;
use HiEvents\Services\Application\Handlers\Cashless\DTO\CreateCashlessPurchaseDTO;
use HiEvents\Services\Application\Handlers\Cashless\Public\CreateCashlessPurchasePublicHandler;
use HiEvents\Services\Application\Locale\LocaleService;
use HiEvents\Services\Domain\Cashless\DTO\CashlessPurchaseItemRequestDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Throwable;

class CreateCashlessPurchasePublicAction extends BaseCashlessSalesPointAction
{
    public function __construct(
        private readonly CreateCashlessPurchasePublicHandler $createCashlessPurchasePublicHandler,
        private readonly LocaleService $localeService,
    ) {}

    /**
     * Charge a ticket's cashless balance
     *
     * `client_reference_id` makes the call idempotent: replaying the same reference returns the
     * transaction that was already recorded instead of debiting twice.
     *
     * @throws Throwable
     */
    public function __invoke(
        CreateCashlessPurchaseRequest $request,
        string $salesPointShortId,
    ): JsonResponse {
        try {
            $transaction = $this->createCashlessPurchasePublicHandler->handle(new CreateCashlessPurchaseDTO(
                sales_point_short_id: $salesPointShortId,
                attendee_public_id: $request->input('attendee_public_id'),
                items: new Collection(array_map(
                    static fn (array $item) => new CashlessPurchaseItemRequestDTO(
                        product_id: (int) $item['product_id'],
                        product_price_id: (int) $item['product_price_id'],
                        quantity: (int) $item['quantity'],
                    ),
                    $request->validated('items'),
                )),
                client_reference_id: $request->input('client_reference_id'),
                locale: $this->localeService->getLocaleOrDefault($request->getPreferredLanguage()),
                session_token: $this->getSessionToken($request),
            ));
        } catch (CashlessSalesPointAccessException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_UNAUTHORIZED);
        } catch (InsufficientCashlessBalanceException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_CONFLICT);
        } catch (CashlessNotEnabledException|CashlessWalletUnavailableException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(
            resource: CashlessTransactionResourcePublic::class,
            data: $transaction,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
