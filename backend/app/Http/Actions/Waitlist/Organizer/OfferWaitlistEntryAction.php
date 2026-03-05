<?php

namespace HiEvents\Http\Actions\Waitlist\Organizer;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\NoCapacityAvailableException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Waitlist\OfferWaitlistEntryRequest;
use HiEvents\Resources\Waitlist\WaitlistEntryResource;
use HiEvents\Services\Application\Handlers\Waitlist\DTO\OfferWaitlistEntryDTO;
use HiEvents\Services\Application\Handlers\Waitlist\OfferWaitlistEntryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class OfferWaitlistEntryAction extends BaseAction
{
    public function __construct(
        private readonly OfferWaitlistEntryHandler $handler,
    )
    {
    }

    public function __invoke(OfferWaitlistEntryRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $entries = $this->handler->handle(new OfferWaitlistEntryDTO(
                event_id: $eventId,
                product_price_id: $request->validated('product_price_id'),
                entry_id: $request->validated('entry_id'),
                quantity: $request->validated('quantity') ?? 1,
            ));
        } catch (NoCapacityAvailableException $exception) {
            throw ValidationException::withMessages([
                'quantity' => $exception->getMessage(),
            ]);
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: WaitlistEntryResource::class,
            data: $entries,
        );
    }
}
