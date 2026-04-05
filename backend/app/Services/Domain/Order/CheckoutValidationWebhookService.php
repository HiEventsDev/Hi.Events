<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\EventSettingDomainObject;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CheckoutValidationWebhookService
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * @throws ValidationException
     */
    public function validate(
        EventSettingDomainObject $eventSettings,
        string                   $orderShortId,
        array                    $orderData,
    ): void
    {
        $webhookUrl = $eventSettings->getCheckoutValidationWebhookUrl();

        if (empty($webhookUrl)) {
            return;
        }

        $payload = [
            'event_type' => 'checkout.validate',
            'event_id' => $eventSettings->getEventId(),
            'order' => [
                'short_id' => $orderShortId,
                'first_name' => $orderData['first_name'] ?? null,
                'last_name' => $orderData['last_name'] ?? null,
                'email' => $orderData['email'] ?? null,
            ],
        ];

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->post($webhookUrl, $payload);

            if ($response->successful()) {
                return;
            }

            if ($response->clientError()) {
                $body = $response->json();
                $message = $body['message'] ?? __('Your order could not be validated. Please contact the event organizer.');

                throw ValidationException::withMessages([
                    'checkout_validation' => $message,
                ]);
            }

            // Server error (5xx)
            Log::warning('Checkout validation webhook returned server error', [
                'url' => $webhookUrl,
                'status' => $response->status(),
                'event_id' => $eventSettings->getEventId(),
            ]);

            throw ValidationException::withMessages([
                'checkout_validation' => __('Unable to validate your order at this time. Please try again later.'),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::warning('Checkout validation webhook failed', [
                'url' => $webhookUrl,
                'error' => $e->getMessage(),
                'event_id' => $eventSettings->getEventId(),
            ]);

            throw ValidationException::withMessages([
                'checkout_validation' => __('Unable to validate your order at this time. Please try again later.'),
            ]);
        }
    }
}
