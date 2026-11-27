<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\ContactResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SplitContactEmailHandler;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SplitContactEmailAction extends BaseCrmAction
{
    public function __construct(
        private readonly SplitContactEmailHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $contactId, int $contactEmailId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $contact = $this->handler->handle($organizerId, $contactId, $contactEmailId);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(ContactResource::class, $contact);
    }
}
