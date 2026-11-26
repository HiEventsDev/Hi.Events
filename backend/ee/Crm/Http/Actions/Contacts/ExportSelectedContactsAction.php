<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Exports\ContactsExport;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\ExportSelectedContactsRequest;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\GetContactsDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\ExportContactsHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\DTO\QueryParamsDTO;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[AllowedForReadOnlyUsers]
class ExportSelectedContactsAction extends BaseCrmAction
{
    public function __construct(
        private readonly ExportContactsHandler $handler,
        private readonly ContactsExport $export,
    ) {}

    public function __invoke(ExportSelectedContactsRequest $request, int $organizerId): StreamedResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->export->download($this->handler->handle(new GetContactsDTO(
            organizer_id: $organizerId,
            query_params: new QueryParamsDTO,
            segment: new SegmentReferenceDTO,
            can_view_financials: $this->canViewFinancials(),
            contact_ids: array_values(array_unique(array_map('intval', $request->validated('contact_ids')))),
        )));
    }
}
