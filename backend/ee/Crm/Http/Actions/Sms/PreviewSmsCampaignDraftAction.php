<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Sms;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\PreviewSmsCampaignRequest;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\DTO\PreviewSmsCampaignDraftDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\PreviewSmsCampaignDraftHandler;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PreviewSmsCampaignDraftAction extends BaseCrmAction
{
    public function __construct(
        private readonly PreviewSmsCampaignDraftHandler $handler,
    ) {}

    public function __invoke(PreviewSmsCampaignRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $preview = $this->handler->handle(new PreviewSmsCampaignDraftDTO(
                account_id: $this->getAuthenticatedAccountId(),
                organizer_id: $organizerId,
                body: $request->validated('body'),
                event_id: $request->validated('event_id') === null ? null : (int) $request->validated('event_id'),
                early_access_product_ids: array_map('intval', $request->validated('early_access_product_ids') ?? []),
                campaign_id: $request->validated('campaign_id') === null ? null : (int) $request->validated('campaign_id'),
                can_view_financials: $this->canViewFinancials(),
                viewer: $this->signedInRecipientName(),
            ));
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $this->jsonResponse($preview->toArray(), wrapInData: true);
    }
}
