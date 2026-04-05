<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Domain\Attendee\BulkAttendeeImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulkImportAttendeesAction extends BaseAction
{
    public function __construct(
        private readonly BulkAttendeeImportService $importService,
    ) {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $validated = $request->validate([
            'csv_file' => 'required_without:csv_content|file|mimes:csv,txt|max:5120',
            'csv_content' => 'required_without:csv_file|string',
            'send_tickets' => 'boolean',
        ]);

        if ($request->hasFile('csv_file')) {
            $csvContent = file_get_contents($request->file('csv_file')->getRealPath());
        } else {
            $csvContent = $validated['csv_content'];
        }

        $parsed = $this->importService->parseAndValidate($csvContent, $eventId);

        if (!empty($parsed['errors']) && empty($parsed['valid'])) {
            return $this->jsonResponse([
                'message' => 'Validation failed. No rows imported.',
                'errors' => $parsed['errors'],
            ], ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->importService->import(
            validRows: $parsed['valid'],
            eventId: $eventId,
            accountId: $this->getAuthenticatedAccountId(),
            sendTickets: $validated['send_tickets'] ?? false,
        );

        return $this->jsonResponse([
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'total_rows' => $result['total_rows'],
            'validation_errors' => $parsed['errors'],
        ], ResponseCodes::HTTP_CREATED);
    }
}
