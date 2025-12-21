<?php

declare(strict_types=1);

namespace HiEvents\Jobs\Vat;

use DateTimeInterface;
use HiEvents\DomainObjects\Status\VatValidationStatus;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Services\Infrastructure\Vat\ViesValidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;
use Throwable;

class ValidateVatNumberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 15;

    public int $maxExceptions = 15;

    public int $timeout = 15;

    public function __construct(
        private readonly int $accountVatSettingId,
        private readonly string $vatNumber,
    ) {}

    public function handle(
        ViesValidationService $viesService,
        AccountVatSettingRepositoryInterface $repository,
        LoggerInterface $logger,
    ): void {
        $logger->info('VAT validation job started', [
            'account_vat_setting_id' => $this->accountVatSettingId,
            'vat_number' => $this->maskVatNumber($this->vatNumber),
            'attempt' => $this->attempts(),
        ]);

        $repository->updateFromArray($this->accountVatSettingId, [
            'vat_validation_status' => VatValidationStatus::VALIDATING->value,
            'vat_validation_attempts' => $this->attempts(),
        ]);

        $result = $viesService->validateVatNumber($this->vatNumber);

        if ($result->valid) {
            $logger->info('VAT validation successful', [
                'account_vat_setting_id' => $this->accountVatSettingId,
                'vat_number' => $this->maskVatNumber($this->vatNumber),
                'business_name' => $result->businessName,
                'attempt' => $this->attempts(),
            ]);

            $repository->updateFromArray($this->accountVatSettingId, [
                'vat_validated' => true,
                'vat_validation_status' => VatValidationStatus::VALID->value,
                'vat_validation_date' => now(),
                'business_name' => $result->businessName,
                'business_address' => $result->businessAddress,
                'vat_country_code' => $result->countryCode,
                'vat_validation_error' => null,
                'vat_validation_attempts' => $this->attempts(),
            ]);

            return;
        }

        if ($result->isTransientError) {
            $logger->warning('VAT validation transient error - will retry', [
                'account_vat_setting_id' => $this->accountVatSettingId,
                'vat_number' => $this->maskVatNumber($this->vatNumber),
                'error' => $result->errorMessage,
                'attempt' => $this->attempts(),
            ]);

            $repository->updateFromArray($this->accountVatSettingId, [
                'vat_validation_status' => VatValidationStatus::PENDING->value,
                'vat_validation_error' => $result->errorMessage,
                'vat_validation_attempts' => $this->attempts(),
            ]);

            $this->release($this->calculateBackoff());

            return;
        }

        $logger->info('VAT validation failed - invalid VAT number', [
            'account_vat_setting_id' => $this->accountVatSettingId,
            'vat_number' => $this->maskVatNumber($this->vatNumber),
            'error' => $result->errorMessage,
            'attempt' => $this->attempts(),
        ]);

        $repository->updateFromArray($this->accountVatSettingId, [
            'vat_validated' => false,
            'vat_validation_status' => VatValidationStatus::INVALID->value,
            'vat_validation_error' => $result->errorMessage,
            'vat_validation_attempts' => $this->attempts(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $logger = app(LoggerInterface::class);
        $repository = app(AccountVatSettingRepositoryInterface::class);

        $logger->error('VAT validation job failed permanently', [
            'account_vat_setting_id' => $this->accountVatSettingId,
            'vat_number' => $this->maskVatNumber($this->vatNumber),
            'error' => $exception->getMessage(),
            'attempt' => $this->attempts(),
        ]);

        try {
            $repository->updateFromArray($this->accountVatSettingId, [
                'vat_validated' => false,
                'vat_validation_status' => VatValidationStatus::FAILED->value,
                'vat_validation_error' => __('Validation failed after multiple attempts: :error', [
                    'error' => $exception->getMessage(),
                ]),
                'vat_validation_attempts' => $this->attempts(),
            ]);
        } catch (Throwable $e) {
            $logger->error('Failed to update VAT setting after job failure', [
                'account_vat_setting_id' => $this->accountVatSettingId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function backoff(): array
    {
        return [
            10,      // 10s
            10,      // 10s
            10,      // 10s
            10,      // 10s
            20,      // 20s
            30,      // 30s
            60,      // 1m
            120,     // 2m
            180,     // 3m
            300,     // 5m
            420,     // 7m
            600,     // 10m
            900,     // 15m
            1200,    // 20m
            1800,    // 30m
        ];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(4);
    }

    private function calculateBackoff(): int
    {
        $backoffs = $this->backoff();
        $attempt = $this->attempts() - 1;

        return $backoffs[$attempt] ?? end($backoffs);
    }

    private function maskVatNumber(string $vatNumber): string
    {
        $length = strlen($vatNumber);
        if ($length <= 4) {
            return $vatNumber;
        }

        return substr($vatNumber, 0, 2) . str_repeat('*', $length - 4) . substr($vatNumber, -2);
    }
}
