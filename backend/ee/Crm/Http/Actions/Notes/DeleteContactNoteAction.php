<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Notes;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Note\DeleteContactNoteHandler;
use Illuminate\Http\Response;

class DeleteContactNoteAction extends BaseCrmAction
{
    public function __construct(
        private readonly DeleteContactNoteHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $contactId, int $noteId): Response
    {
        $this->authorizeCrm($organizerId);

        $this->handler->handle($organizerId, $contactId, $noteId);

        return $this->deletedResponse();
    }
}
