<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Public;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignDispatchService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsWebhookService;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwilioSmsWebhookPublicAction extends BaseAction
{
    public const KIND_STATUS = 'status';

    public const KIND_INBOUND = 'inbound';

    private const STATUS_CALLBACKS_PER_TEXT = 4;

    private const ALLOWANCE_HEADROOM = 1.5;

    public static function maxRequestsPerMinute(string $kind): int
    {
        $textsPerMinute = CampaignDispatchService::WAVE_SIZE * 60 / CampaignDispatchService::WAVE_DELAY_SECONDS;
        $requestsPerText = $kind === self::KIND_STATUS ? self::STATUS_CALLBACKS_PER_TEXT : 1;

        return (int) ceil($textsPerMinute * $requestsPerText * self::ALLOWANCE_HEADROOM);
    }

    public function __construct(
        private readonly SmsWebhookService $webhookService,
    ) {}

    public function __invoke(Request $request, string $publicId, string $kind): Response
    {
        $params = array_map(static fn (mixed $value) => is_scalar($value) ? (string) $value : '', $request->post());
        $isInbound = $kind === self::KIND_INBOUND;
        $connection = $this->webhookService->authenticate($publicId, $isInbound, $params, (string) $request->header('X-Twilio-Signature'));

        if ($connection === null) {
            return $this->noContentResponse(ResponseCodes::HTTP_UNAUTHORIZED);
        }

        if ($isInbound) {
            $this->webhookService->recordReply($connection, (string) ($params['From'] ?? ''), (string) ($params['Body'] ?? ''), (string) ($params['OptOutType'] ?? ''));
        } else {
            $this->webhookService->recordStatus($connection, (string) ($params['MessageSid'] ?? ''), (string) ($params['MessageStatus'] ?? ''));
        }

        return $this->noContentResponse(ResponseCodes::HTTP_OK);
    }
}
