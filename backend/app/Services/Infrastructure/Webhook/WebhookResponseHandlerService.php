<?php

namespace HiEvents\Services\Infrastructure\Webhook;

use GuzzleHttp\Psr7\Response;
use HiEvents\Repository\Interfaces\WebhookLogRepositoryInterface;
use HiEvents\Repository\Interfaces\WebhookRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;

class WebhookResponseHandlerService
{
    public function __construct(
        private readonly WebhookRepositoryInterface    $webhookRepository,
        private readonly LoggerInterface               $logger,
        private readonly WebhookLogRepositoryInterface $webhookLogRepository,
        private readonly DatabaseManager               $databaseManager,
    )
    {
    }

    public function handleResponse(
        int       $eventId,
        int       $webhookId,
        string    $eventType,
        array     $payload,
        ?Response $response
    ): void
    {
        $this->databaseManager->transaction(function () use ($payload, $eventType, $eventId, $webhookId, $response) {
            $webhook = $this->webhookRepository->findFirstWhere([
                'id' => $webhookId,
                'event_id' => $eventId,
            ]);

            if (!$webhook) {
                $this->logger->error("Webhook not found for ID: $webhookId and event ID: $eventId");
                return;
            }

            $status = $response?->getStatusCode() ?? 0;
            $responseBody = $response ? substr($response->getBody()->getContents(), 0, 1000) : null;

            $this->webhookRepository->updateWhere(
                attributes: [
                    'last_response_code' => $status,
                    'last_response_body' => $responseBody,
                    'last_triggered_at' => now(),
                ],
                where: [
                    'id' => $webhookId,
                    'event_id' => $eventId,
                ]);

            $this->webhookLogRepository->create([
                'webhook_id' => $webhook->getId(),
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'response_code' => $status,
                'event_type' => $eventType,
                'response_body' => $responseBody,
            ]);

            $this->webhookLogRepository->deleteOldLogs($webhookId);
        });
    }
}
