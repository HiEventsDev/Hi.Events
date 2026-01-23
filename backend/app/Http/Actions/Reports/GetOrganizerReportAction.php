<?php

namespace HiEvents\Http\Actions\Reports;

use HiEvents\DomainObjects\Enums\OrganizerReportTypes;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Report\GetOrganizerReportRequest;
use HiEvents\Services\Application\Handlers\Reports\DTO\GetOrganizerReportDTO;
use HiEvents\Services\Application\Handlers\Reports\GetOrganizerReportHandler;
use HiEvents\Services\Domain\Report\DTO\PaginatedReportDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GetOrganizerReportAction extends BaseAction
{
    public function __construct(private readonly GetOrganizerReportHandler $reportHandler)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(GetOrganizerReportRequest $request, int $organizerId, string $reportType): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $this->validateDateRange($request);

        if (!in_array($reportType, OrganizerReportTypes::valuesArray(), true)) {
            throw new BadRequestHttpException(__('Invalid report type.'));
        }

        $reportData = $this->reportHandler->handle(
            reportData: new GetOrganizerReportDTO(
                organizerId: $organizerId,
                reportType: OrganizerReportTypes::from($reportType),
                startDate: $request->validated('start_date'),
                endDate: $request->validated('end_date'),
                currency: $request->validated('currency'),
                eventId: $request->validated('event_id'),
                page: (int) $request->validated('page', 1),
                perPage: (int) $request->validated('per_page', 1000),
            ),
        );

        if ($reportData instanceof PaginatedReportDTO) {
            return $this->jsonResponse(
                data: $reportData->toArray(),
            );
        }

        return $this->jsonResponse(
            data: $reportData,
            wrapInData: true,
        );
    }

    /**
     * @throws ValidationException
     */
    private function validateDateRange(GetOrganizerReportRequest $request): void
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        if (!$startDate || !$endDate) {
            return;
        }

        $diffInDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));

        if ($diffInDays > 370) {
            throw ValidationException::withMessages(['start_date' => __('Date range must be less than 370 days.')]);
        }
    }
}
