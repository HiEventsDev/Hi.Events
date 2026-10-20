<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Lineup\DeleteProfileHandler;
use Illuminate\Http\Response;

class DeleteProfileAction extends BaseAction
{
    public function __construct(
        private readonly DeleteProfileHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $profileId): Response
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $this->handler->handle($profileId, $organizerId, $this->getAuthenticatedAccountId());

        return $this->deletedResponse();
    }
}
