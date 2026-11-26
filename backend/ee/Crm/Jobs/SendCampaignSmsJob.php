<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsCampaignDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendCampaignSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const RETRY_WINDOW_MINUTES = 120;

    private const PROVIDER_RETRY_DELAYS_SECONDS = [30, 120, 300, 600];

    public int $maxExceptions = 3;

    public int $timeout = 30;

    /**
     * @var int[]
     */
    public array $backoff = [30, 120];

    public function __construct(
        public int $recipientId,
    ) {}

    public function retryUntil(): CarbonInterface
    {
        return now()->addMinutes(self::RETRY_WINDOW_MINUTES);
    }

    public function handle(SmsCampaignDeliveryService $deliveryService): void
    {
        try {
            $deliveryService->deliver($this->recipientId);
        } catch (SmsProviderException) {
            $this->retryLaterOrGiveUp($deliveryService);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(SmsCampaignDeliveryService::class)->markFailed($this->recipientId, CrmRecipientFailureReason::PROVIDER_UNAVAILABLE);
    }

    private function retryLaterOrGiveUp(SmsCampaignDeliveryService $deliveryService): void
    {
        $delay = self::PROVIDER_RETRY_DELAYS_SECONDS[min($this->attempts(), count(self::PROVIDER_RETRY_DELAYS_SECONDS)) - 1];
        $retryUntil = $this->job?->retryUntil();

        if ($retryUntil !== null && now()->addSeconds($delay)->getTimestamp() >= $retryUntil) {
            $deliveryService->markFailed($this->recipientId, CrmRecipientFailureReason::PROVIDER_UNAVAILABLE);

            return;
        }

        $this->release($delay);
    }
}
