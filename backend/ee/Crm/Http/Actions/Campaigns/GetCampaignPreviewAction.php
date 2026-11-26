<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\GetCampaignPreviewHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignSenderName;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsPreviewDTO;
use Illuminate\Http\JsonResponse;

class GetCampaignPreviewAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetCampaignPreviewHandler $handler,
        private readonly CampaignSenderName $senderName,
    ) {}

    public function __invoke(int $organizerId, int $campaignId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        $preview = $this->handler->handle($organizerId, $campaignId, $this->signedInRecipientName());

        if ($preview instanceof SmsPreviewDTO) {
            return $this->jsonResponse($preview->toArray(), wrapInData: true);
        }

        return $this->jsonResponse([
            'html' => $preview->renderAsSent(),
            'subject' => $preview->getSubjectLine(),
            'preheader' => $preview->getPreheader(),
            'from_name' => $this->senderName->for($preview),
            'reply_to' => $preview->getReplyToAddress(),
        ], wrapInData: true);
    }
}
