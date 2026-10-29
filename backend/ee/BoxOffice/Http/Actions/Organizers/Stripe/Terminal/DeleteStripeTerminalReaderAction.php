<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Organizers\Stripe\Terminal;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Organizer\Payment\Stripe\Terminal\DeleteStripeTerminalReaderHandler;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use Illuminate\Http\Response;

#[RequiresPermission(Permission::MANAGE_PAYMENTS)]
class DeleteStripeTerminalReaderAction extends BaseAction
{
    public function __construct(
        private readonly DeleteStripeTerminalReaderHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $readerId): Response
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $this->handler->handle($organizerId, $this->getAuthenticatedAccountId(), $readerId);

        return $this->noContentResponse();
    }
}
