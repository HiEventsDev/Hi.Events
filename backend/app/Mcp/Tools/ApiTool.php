<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteDispatcher;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

abstract class ApiTool extends Tool
{
    public function __construct(
        protected readonly ApiRouteDispatcher $api,
    ) {}

    protected function getEvent(int $eventId): ApiRouteResponseDTO
    {
        return $this->api->get(GetEventAction::class, ['event_id' => $eventId]);
    }

    protected function errorResponse(ApiRouteResponseDTO $response): Response
    {
        return Response::error($this->errorMessage($response));
    }

    protected function errorMessage(ApiRouteResponseDTO $response): string
    {
        $message = (string) ($response->body['message'] ?? '');
        $fieldMessages = $this->fieldMessages($response);

        return match (true) {
            $response->status === ResponseCodes::HTTP_UNPROCESSABLE_ENTITY && $fieldMessages !== '' => $fieldMessages,
            $response->status === ResponseCodes::HTTP_NOT_FOUND => __('Not found in this account.'),
            $response->status === ResponseCodes::HTTP_TOO_MANY_REQUESTS => __('Too many requests. Wait a minute and try again.'),
            $response->status < ResponseCodes::HTTP_INTERNAL_SERVER_ERROR && $message !== '' => $message,
            default => __('Hi.Events could not complete the request (HTTP :status).', ['status' => $response->status]),
        };
    }

    private function fieldMessages(ApiRouteResponseDTO $response): string
    {
        return collect($response->body['errors'] ?? [])
            ->map(static fn (array|string $messages, string $field): string => $field.': '.implode(' ', (array) $messages))
            ->implode("\n");
    }
}
