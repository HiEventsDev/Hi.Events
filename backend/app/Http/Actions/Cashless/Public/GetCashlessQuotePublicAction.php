<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Public;

use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Http\Request\Cashless\CreateCashlessQuoteRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessQuoteResource;
use HiEvents\Services\Application\Handlers\Cashless\Public\GetCashlessQuotePublicHandler;
use HiEvents\Services\Domain\Cashless\DTO\CashlessPurchaseItemRequestDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class GetCashlessQuotePublicAction extends BaseCashlessSalesPointAction
{
    public function __construct(
        private readonly GetCashlessQuotePublicHandler $getCashlessQuotePublicHandler,
    ) {}

    /**
     * Price a basket or a top-up before charging it
     *
     * Lets a till show the exact amount to take, taxes and fees included, so staff key the right
     * total into a card terminal.
     */
    public function __invoke(CreateCashlessQuoteRequest $request, string $salesPointShortId): JsonResponse
    {
        $topupAmount = $request->input('topup_amount');

        try {
            $quote = $this->getCashlessQuotePublicHandler->handle(
                salesPointShortId: $salesPointShortId,
                sessionToken: $this->getSessionToken($request),
                items: new Collection(array_map(
                    static fn (array $item) => new CashlessPurchaseItemRequestDTO(
                        product_id: (int) $item['product_id'],
                        product_price_id: (int) $item['product_price_id'],
                        quantity: (int) $item['quantity'],
                    ),
                    $request->input('items', []) ?? [],
                )),
                topupAmount: $topupAmount === null ? null : (float) $topupAmount,
            );
        } catch (CashlessSalesPointAccessException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_UNAUTHORIZED);
        } catch (CashlessNotEnabledException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(
            resource: CashlessQuoteResource::class,
            data: $quote,
        );
    }
}
