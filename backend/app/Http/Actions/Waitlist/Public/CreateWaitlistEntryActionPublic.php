<?php

namespace HiEvents\Http\Actions\Waitlist\Public;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Waitlist\CreateWaitlistEntryRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Waitlist\WaitlistEntryResource;
use HiEvents\Services\Application\Handlers\Waitlist\CreateWaitlistEntryHandler;
use HiEvents\Services\Application\Handlers\Waitlist\DTO\CreateWaitlistEntryDTO;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreateWaitlistEntryActionPublic extends BaseAction
{
    public function __construct(
        private readonly CreateWaitlistEntryHandler $handler,
    )
    {
    }

    public function __invoke(CreateWaitlistEntryRequest $request, int $eventId): JsonResponse
    {
        try {
            $entry = $this->handler->handle(new CreateWaitlistEntryDTO(
                event_id: $eventId,
                product_price_id: $request->validated('product_price_id'),
                email: $request->validated('email'),
                first_name: $request->validated('first_name'),
                last_name: $request->validated('last_name'),
                locale: $request->input('locale', 'en'),
            ));
        } catch (ResourceConflictException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_CONFLICT,
            );
        }

        return $this->resourceResponse(
            resource: WaitlistEntryResource::class,
            data: $entry,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
