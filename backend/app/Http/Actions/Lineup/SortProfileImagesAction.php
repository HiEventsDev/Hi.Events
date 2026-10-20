<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Lineup\SortProfileImagesRequest;
use HiEvents\Services\Application\Handlers\Lineup\SortProfileImagesHandler;
use Illuminate\Http\Response;

class SortProfileImagesAction extends BaseAction
{
    public function __construct(
        private readonly SortProfileImagesHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $profileId, SortProfileImagesRequest $request): Response
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $this->handler->handle(
            organizerId: $organizerId,
            accountId: $this->getAuthenticatedAccountId(),
            profileId: $profileId,
            sortedImageIds: array_map('intval', $request->validated('sorted_ids')),
        );

        return $this->noContentResponse();
    }
}
