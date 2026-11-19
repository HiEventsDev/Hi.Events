<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Exports\ContactsExport;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\GetContactsDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\ExportContactsHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportContactsAction extends BaseCrmAction
{
    public function __construct(
        private readonly ExportContactsHandler $handler,
        private readonly ContactsExport $export,
    ) {}

    public function __invoke(Request $request, int $organizerId): StreamedResponse
    {
        $this->authorizeCrm($organizerId);

        try {
            $pages = $this->handler->handle(new GetContactsDTO(
                organizer_id: $organizerId,
                query_params: $this->getPaginationQueryParams($request),
                segment: $this->segmentReference($request),
                can_view_financials: $this->canViewFinancials(),
            ));
        } catch (InvalidSegmentDefinitionException $exception) {
            throw $this->invalidSegment($exception);
        }

        return $this->export->download($pages);
    }
}
