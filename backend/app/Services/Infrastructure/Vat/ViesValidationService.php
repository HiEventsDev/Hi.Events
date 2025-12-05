<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Vat;

use Exception;
use HiEvents\Services\Application\Handlers\Account\Vat\DTO\ViesValidationResponseDTO;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClient;
use Psr\Log\LoggerInterface;

class ViesValidationService
{
    private const VIES_API_URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';
    private const TIMEOUT_SECONDS = 15;

    private const TRANSIENT_ERRORS = [
        'MS_MAX_CONCURRENT_REQ',
        'MS_UNAVAILABLE',
        'TIMEOUT',
        'SERVER_BUSY',
        'SERVICE_UNAVAILABLE',
        'GLOBAL_MAX_CONCURRENT_REQ',
    ];

    public function __construct(
        private readonly HttpClient      $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function validateVatNumber(string $vatNumber): ViesValidationResponseDTO
    {
        $countryCode = substr($vatNumber, 0, 2);
        $vatNumberOnly = substr($vatNumber, 2);

        $this->logger->info('VIES validation request started', [
            'vat_number' => $this->maskVatNumber($vatNumber),
            'country_code' => $countryCode,
        ]);

        try {
            $response = $this->httpClient
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::VIES_API_URL, [
                    'countryCode' => $countryCode,
                    'vatNumber' => $vatNumberOnly,
                ]);

            $data = $response->json() ?? [];

            $this->logger->info('VIES validation response received', [
                'vat_number' => $this->maskVatNumber($vatNumber),
                'status_code' => $response->status(),
                'action_succeed' => $data['actionSucceed'] ?? null,
                'valid' => $data['valid'] ?? null,
                'has_errors' => !empty($data['errorWrappers']),
            ]);

            if (!$response->successful()) {
                $this->logger->warning('VIES HTTP error response', [
                    'vat_number' => $this->maskVatNumber($vatNumber),
                    'status_code' => $response->status(),
                    'body' => $response->body(),
                ]);

                return new ViesValidationResponseDTO(
                    valid: false,
                    countryCode: $countryCode,
                    vatNumber: $vatNumberOnly,
                    isTransientError: true,
                    errorMessage: __('VIES service returned HTTP :status', ['status' => $response->status()]),
                );
            }

            if (($data['actionSucceed'] ?? true) === false || !empty($data['errorWrappers'])) {
                $errorCode = $this->extractErrorCode($data);

                $this->logger->warning('VIES API returned error', [
                    'vat_number' => $this->maskVatNumber($vatNumber),
                    'error_code' => $errorCode,
                    'response' => $data,
                ]);

                $isTransient = $this->isTransientError($errorCode);

                return new ViesValidationResponseDTO(
                    valid: false,
                    countryCode: $countryCode,
                    vatNumber: $vatNumberOnly,
                    isTransientError: $isTransient,
                    errorMessage: $this->formatErrorMessage($errorCode),
                );
            }

            $isValid = $data['valid'] ?? false;

            $this->logger->info('VIES validation completed', [
                'vat_number' => $this->maskVatNumber($vatNumber),
                'valid' => $isValid,
                'business_name' => $data['name'] ?? null,
            ]);

            return new ViesValidationResponseDTO(
                valid: $isValid,
                businessName: $data['name'] ?? null,
                businessAddress: $data['address'] ?? null,
                countryCode: $countryCode,
                vatNumber: $vatNumberOnly,
                isTransientError: false,
                errorMessage: $isValid ? null : __('VAT number is not valid according to VIES'),
            );

        } catch (ConnectionException $e) {
            $this->logger->error('VIES connection error', [
                'vat_number' => $this->maskVatNumber($vatNumber),
                'error' => $e->getMessage(),
            ]);

            return new ViesValidationResponseDTO(
                valid: false,
                countryCode: $countryCode,
                vatNumber: $vatNumberOnly,
                isTransientError: true,
                errorMessage: __('Connection error: :error', ['error' => $e->getMessage()]),
            );

        } catch (Exception $e) {
            $this->logger->error('VIES validation exception', [
                'vat_number' => $this->maskVatNumber($vatNumber),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            return new ViesValidationResponseDTO(
                valid: false,
                countryCode: $countryCode,
                vatNumber: $vatNumberOnly,
                isTransientError: true,
                errorMessage: __('Validation error: :error', ['error' => $e->getMessage()]),
            );
        }
    }

    private function extractErrorCode(array $data): ?string
    {
        if (empty($data['errorWrappers'])) {
            return null;
        }

        return $data['errorWrappers'][0]['error'] ?? null;
    }

    private function isTransientError(?string $errorCode): bool
    {
        if ($errorCode === null) {
            return true;
        }

        return in_array($errorCode, self::TRANSIENT_ERRORS, true);
    }

    private function formatErrorMessage(?string $errorCode): string
    {
        return match ($errorCode) {
            'MS_MAX_CONCURRENT_REQ' => __('VIES service is temporarily busy. Validation will be retried.'),
            'MS_UNAVAILABLE' => __('Member State service is temporarily unavailable. Validation will be retried.'),
            'TIMEOUT' => __('VIES service timed out. Validation will be retried.'),
            'SERVER_BUSY' => __('VIES server is busy. Validation will be retried.'),
            'SERVICE_UNAVAILABLE' => __('VIES service is unavailable. Validation will be retried.'),
            'GLOBAL_MAX_CONCURRENT_REQ' => __('VIES service has reached maximum requests. Validation will be retried.'),
            'INVALID_INPUT' => __('Invalid VAT number format.'),
            'INVALID_REQUESTER_INFO' => __('Invalid requester information.'),
            default => __('VIES validation error: :code', ['code' => $errorCode ?? 'unknown']),
        };
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
