<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Exceptions\InvalidEarlyAccessTicketsException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\UpsertCampaignRequest;
use HiEvents\Enterprise\Crm\Resources\CampaignResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\DTO\UpsertCampaignDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\UpsertCampaignHandler;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class UpdateCampaignAction extends BaseCrmAction
{
    public function __construct(
        private readonly UpsertCampaignHandler $handler,
    ) {}

    public function __invoke(UpsertCampaignRequest $request, int $organizerId, int $campaignId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $campaign = $this->handler->handle(new UpsertCampaignDTO(
                account_id: $this->getAuthenticatedAccountId(),
                organizer_id: $organizerId,
                user_id: $this->getAuthenticatedUser()->getId(),
                name: $request->validated('name'),
                subject: $request->validated('subject'),
                body: $request->validated('body'),
                segment: $this->segmentReference($request),
                event_id: $request->validated('event_id') === null ? null : (int) $request->validated('event_id'),
                can_view_financials: $this->canViewFinancials(),
                campaign_id: $campaignId,
                preheader: $request->validated('preheader'),
                early_access_product_ids: array_map('intval', $request->validated('early_access_product_ids') ?? []),
                template_id: $request->validated('template_id') === null ? null : (int) $request->validated('template_id'),
            ));
        } catch (InvalidSegmentDefinitionException $exception) {
            throw $this->invalidSegment($exception);
        } catch (InvalidEarlyAccessTicketsException $exception) {
            throw ValidationException::withMessages(['early_access_product_ids' => $exception->getMessage()]);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(CampaignResource::class, $campaign);
    }
}
