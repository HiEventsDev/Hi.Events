<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\ContactResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\GetContactsDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\GetContactsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetContactsAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetContactsHandler $handler,
    ) {}

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        try {
            $contacts = $this->handler->handle(new GetContactsDTO(
                organizer_id: $organizerId,
                query_params: $this->getPaginationQueryParams($request),
                segment: $this->segmentReference($request),
                can_view_financials: $this->canViewFinancials(),
                with_suppression: $request->boolean('with_suppression'),
            ));
        } catch (InvalidSegmentDefinitionException $exception) {
            throw $this->invalidSegment($exception);
        }

        return $this->filterableResourceResponse(
            resource: ContactResource::class,
            data: $contacts,
            domainObject: CrmContactDomainObject::class,
        );
    }
}
