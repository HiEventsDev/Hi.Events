<?php

namespace HiEvents\Http\Actions\Waitlist\Public;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Waitlist\CancelWaitlistEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CancelWaitlistEntryActionPublic extends BaseAction
{
    public function __construct(
        private readonly CancelWaitlistEntryService $cancelWaitlistEntryService,
    )
    {
    }

    public function __invoke(int $eventId, string $token): Response|JsonResponse
    {
        try {
            $this->cancelWaitlistEntryService->cancelByToken($token, $eventId);
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: SymfonyResponse::HTTP_NOT_FOUND,
            );
        } catch (ResourceConflictException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: SymfonyResponse::HTTP_CONFLICT,
            );
        }

        return $this->noContentResponse();
    }
}
