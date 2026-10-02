<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Organizers\Stripe\Terminal;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Organizer\Payment\Stripe\Terminal\DeleteStripeTerminalReaderHandler;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\Response;

class DeleteStripeTerminalReaderAction extends BaseAction
{
    public function __construct(
        private readonly DeleteStripeTerminalReaderHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $readerId): Response
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class, Role::ADMIN);

        $this->handler->handle($organizerId, $this->getAuthenticatedAccountId(), $readerId);

        return $this->noContentResponse();
    }
}
