<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\SpamEvents;

use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Admin\DeleteSpamEventRequest;
use HiEvents\Services\Application\Handlers\Admin\DeleteSpamEventHandler;
use HiEvents\Services\Application\Handlers\Admin\DTO\DeleteSpamEventDTO;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class DeleteSpamEventAction extends BaseAction
{
    public function __construct(
        private readonly DeleteSpamEventHandler $handler,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(DeleteSpamEventRequest $request, int $eventId): JsonResponse
    {
        $this->requireSuperAdmin();

        $deleteAccount = $request->boolean('delete_account');

        try {
            $this->handler->handle(new DeleteSpamEventDTO(
                eventId: $eventId,
                reviewedByUserId: $this->getAuthenticatedUser()->getId(),
                deleteAccount: $deleteAccount,
            ));
        } catch (CannotDeleteEntityException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: HttpResponse::HTTP_CONFLICT,
            );
        }

        return $this->jsonResponse([
            'message' => $deleteAccount
                ? __('Event deleted and account deletion queued')
                : __('Event deleted'),
        ]);
    }
}
