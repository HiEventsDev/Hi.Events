<?php

namespace HiEvents\Http\Actions\Organizers;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Organizer\OrganizerResourcePublic;
use HiEvents\Services\Application\Handlers\Organizer\GetPublicOrganizerHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;

class GetPublicOrganizerAction extends BaseAction
{
    public function __construct(
        private readonly GetPublicOrganizerHandler $handler,
        private readonly LoggerInterface           $logger,

    )
    {
    }

    public function __invoke(int $organizerId): Response|JsonResponse
    {
        $organizer = $this->handler->handle($organizerId);

        if (!$this->canUserViewOrganizer($organizer)) {
            $this->logger->debug(__('Organizer with ID :organizerId is not live and user is not authenticated', [
                'organizerId' => $organizer->getId(),
            ]));

            return $this->notFoundResponse();
        }

        return $this->resourceResponse(
            resource: OrganizerResourcePublic::class,
            data: $this->handler->handle($organizerId),
        );
    }

    private function canUserViewOrganizer(OrganizerDomainObject $organizer): bool
    {
        if ($organizer->getStatus() === OrganizerStatus::LIVE->name) {
            return true;
        }

        if ($this->isUserAuthenticated() && $organizer->getAccountId() === $this->getAuthenticatedAccountId()) {
            return true;
        }

        return false;
    }
}
