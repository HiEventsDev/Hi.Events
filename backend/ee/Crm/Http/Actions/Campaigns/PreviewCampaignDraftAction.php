<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\PreviewCampaignRequest;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\DTO\PreviewCampaignDraftDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\PreviewCampaignDraftHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignSenderName;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PreviewCampaignDraftAction extends BaseCrmAction
{
    public function __construct(
        private readonly PreviewCampaignDraftHandler $handler,
        private readonly CampaignSenderName $senderName,
    ) {}

    public function __invoke(PreviewCampaignRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $mail = $this->handler->handle(new PreviewCampaignDraftDTO(
                account_id: $this->getAuthenticatedAccountId(),
                organizer_id: $organizerId,
                subject: $request->validated('subject'),
                preheader: $request->validated('preheader'),
                body: $request->validated('body'),
                event_id: $request->validated('event_id') === null ? null : (int) $request->validated('event_id'),
                early_access_product_ids: array_map('intval', $request->validated('early_access_product_ids') ?? []),
                contact_id: $request->validated('contact_id') === null ? null : (int) $request->validated('contact_id'),
                viewer: $this->signedInRecipientName(),
            ));
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $this->jsonResponse([
            'html' => $mail->render(),
            'subject' => $mail->getSubjectLine(),
            'preheader' => $mail->getPreheader(),
            'from_name' => $this->senderName->for($mail),
            'reply_to' => $mail->getReplyToAddress(),
        ], wrapInData: true);
    }
}
