<?php

namespace HiEvents\Http\Actions\Reports;

use HiEvents\DomainObjects\Enums\ReportTypes;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Report\GetReportRequest;
use HiEvents\Services\Application\Handlers\Reports\DTO\GetReportDTO;
use HiEvents\Services\Application\Handlers\Reports\GetReportHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GetReportAction extends BaseAction
{
    public function __construct(private readonly GetReportHandler $reportHandler)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(GetReportRequest $request, int $eventId, string $reportType): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->validateDateRange($request);

        if (!in_array($reportType, ReportTypes::valuesArray(), true)) {
            throw new BadRequestHttpException('Invalid report type.');
        }

        $reportData = $this->reportHandler->handle(
            reportData: new GetReportDTO(
                eventId: $eventId,
                reportType: ReportTypes::from($reportType),
                startDate: $request->validated('start_date'),
                endDate: $request->validated('end_date'),
            ),
        );

        return $this->jsonResponse($reportData);
    }

    /**
     * @throws ValidationException
     */
    private function validateDateRange(GetReportRequest $request): void
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        $diffInDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));

        if ($diffInDays > 370) {
            throw ValidationException::withMessages(['start_date' => 'Date range must be less than 370 days.']);
        }
    }
}
