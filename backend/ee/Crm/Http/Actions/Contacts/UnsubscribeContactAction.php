<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\UnsubscribeContactRequest;
use HiEvents\Enterprise\Crm\Resources\ContactResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\UnsubscribeContactHandler;
use Illuminate\Http\JsonResponse;

class UnsubscribeContactAction extends BaseCrmAction
{
    public function __construct(
        private readonly UnsubscribeContactHandler $handler,
    ) {}

    public function __invoke(UnsubscribeContactRequest $request, int $organizerId, int $contactId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->resourceResponse(ContactResource::class, $this->handler->handle(
            $organizerId,
            $contactId,
            $this->getAuthenticatedUser()->getId(),
            CrmMarketingChannel::from($request->validated('channel') ?? CrmMarketingChannel::EMAIL->value),
        ));
    }
}
