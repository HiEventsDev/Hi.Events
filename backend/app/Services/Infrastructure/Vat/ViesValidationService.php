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
    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly HttpClient      $httpClient,
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function validateVatNumber(string $vatNumber): ViesValidationResponseDTO
    {
        $countryCode = substr($vatNumber, 0, 2);
        $vatNumberOnly = substr($vatNumber, 2);

        try {
            $response = $this->httpClient->timeout(self::TIMEOUT_SECONDS)->post(self::VIES_API_URL, [
                'countryCode' => $countryCode,
                'vatNumber' => $vatNumberOnly,
            ]);

            if (!$response->successful()) {
                $this->logger->warning('VIES API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'vat_number' => $vatNumber,
                ]);

                return new ViesValidationResponseDTO(
                    valid: false,
                    countryCode: $countryCode,
                    vatNumber: $vatNumberOnly,
                );
            }

            $data = $response->json();

            return new ViesValidationResponseDTO(
                valid: $data['valid'] ?? false,
                businessName: $data['name'] ?? null,
                businessAddress: $data['address'] ?? null,
                countryCode: $countryCode,
                vatNumber: $vatNumberOnly,
            );
        } catch (ConnectionException $e) {
            $this->logger->error('VIES API connection error', [
                'error' => $e->getMessage(),
                'vat_number' => $vatNumber,
            ]);

            return new ViesValidationResponseDTO(
                valid: false,
                countryCode: $countryCode,
                vatNumber: $vatNumberOnly,
            );
        } catch (Exception $e) {
            $this->logger->error('VIES validation exception', [
                'error' => $e->getMessage(),
                'vat_number' => $vatNumber,
            ]);

            return new ViesValidationResponseDTO(
                valid: false,
                countryCode: $countryCode,
                vatNumber: $vatNumberOnly,
            );
        }
    }
}
