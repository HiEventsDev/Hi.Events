<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Http\Request\CreateBoxOfficeOrderPublicRequest;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\CreateBoxOfficeOrderPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeBuyerDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeDiscountDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeOrderItemDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\CreateBoxOfficeOrderDTO;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Services\Application\Locale\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CreateBoxOfficeOrderPublicAction extends BaseAction
{
    public function __construct(
        private readonly CreateBoxOfficeOrderPublicHandler $handler,
        private readonly LocaleService $localeService,
    ) {}

    public function __invoke(CreateBoxOfficeOrderPublicRequest $request, string $boxOfficeShortId): JsonResponse
    {
        $buyer = $request->validated('buyer') ?? [];
        $discount = $request->validated('discount');

        try {
            $order = $this->handler->handle(new CreateBoxOfficeOrderDTO(
                box_office: $request->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE),
                session: $request->attributes->get(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE),
                idempotency_key: $request->validated('idempotency_key'),
                items: collect($request->validated('items'))->map(fn (array $item) => new BoxOfficeOrderItemDTO(
                    product_id: (int) $item['product_id'],
                    product_price_id: (int) $item['product_price_id'],
                    quantity: (int) $item['quantity'],
                    override_price: isset($item['override_price']) ? (float) $item['override_price'] : null,
                    seat_uids: $item['seat_uids'] ?? [],
                )),
                discount: $discount ? new BoxOfficeDiscountDTO(type: $discount['type'], value: (float) $discount['value']) : null,
                buyer: new BoxOfficeBuyerDTO(
                    first_name: $this->cleanText($buyer['first_name'] ?? null),
                    last_name: $this->cleanText($buyer['last_name'] ?? null),
                    email: $buyer['email'] ?? null,
                ),
                questions: $request->validated('questions') ?? [],
                locale: $this->localeService->getLocaleOrDefault($request->getPreferredLanguage()),
                ip_address: $request->ip(),
                user_agent: $request->userAgent(),
                attendees: $request->validated('attendees') ?? [],
            ));
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        } catch (SeatsUnavailableException $exception) {
            return $this->errorResponse(
                message: __('Some of the selected seats have just been taken'),
                statusCode: Response::HTTP_CONFLICT,
                errors: ['unavailable_seat_uids' => $exception->getSeatUids()],
            );
        } catch (SeatSelectionInvalidException $exception) {
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        }

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
            statusCode: Response::HTTP_CREATED,
        );
    }

    private function cleanText(?string $value): ?string
    {
        $clean = $value === null ? null : strip_tags(trim($value));

        return $clean === '' ? null : $clean;
    }
}
