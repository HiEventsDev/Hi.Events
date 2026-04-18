<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Contacts\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Contact\LookupContactByEmailRequest;
use HiEvents\Services\Application\Handlers\Contact\DTO\LookupContactByEmailPublicDTO;
use HiEvents\Services\Application\Handlers\Contact\LookupContactByEmailPublicHandler;
use Illuminate\Http\JsonResponse;

class LookupContactByEmailPublicAction extends BaseAction
{
    public function __construct(
        private readonly LookupContactByEmailPublicHandler $handler,
    ) {}

    public function __invoke(LookupContactByEmailRequest $request, int $eventId): JsonResponse
    {
        $result = $this->handler->handle(new LookupContactByEmailPublicDTO(
            eventId: $eventId,
            email: (string) $request->input('email'),
        ));

        return $this->jsonResponse($result->toArray());
    }
}
