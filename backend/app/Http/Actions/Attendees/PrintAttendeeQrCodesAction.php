<?php

namespace HiEvents\Http\Actions\Attendees;

use Barryvdh\DomPDF\Facade\Pdf;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PrintAttendeeQrCodesAction extends BaseAction
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    )
    {
    }

    public function __invoke(Request $request, int $eventId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $attendeeIds = $request->input('attendee_ids', []);
        $labelSize = $request->input('label_size', '30x20'); // mm
        $columns = (int) $request->input('columns', 3);

        if (empty($attendeeIds)) {
            // Get all attendees for the event
            $attendees = $this->attendeeRepository->findWhere([
                'event_id' => $eventId,
            ]);
        } else {
            $attendees = $this->attendeeRepository->findWhereIn(
                'id',
                $attendeeIds,
            );
        }

        $labelDimensions = $this->parseLabelSize($labelSize);

        $pdf = Pdf::loadView('qr-codes.attendee-labels', [
            'attendees' => $attendees,
            'labelWidth' => $labelDimensions['width'],
            'labelHeight' => $labelDimensions['height'],
            'columns' => $columns,
            'eventId' => $eventId,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream(sprintf('qr-codes-event-%d.pdf', $eventId));
    }

    private function parseLabelSize(string $size): array
    {
        $parts = explode('x', $size);
        return [
            'width' => (int) ($parts[0] ?? 30),
            'height' => (int) ($parts[1] ?? 20),
        ];
    }
}
