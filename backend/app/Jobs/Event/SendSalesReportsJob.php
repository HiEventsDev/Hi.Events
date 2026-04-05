<?php

namespace HiEvents\Jobs\Event;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Mail\Organizer\SalesReportMail;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\EventStatsRequestDTO;
use HiEvents\Services\Domain\Event\EventStatsFetchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSalesReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(
        EventRepositoryInterface         $eventRepository,
        EventSettingsRepositoryInterface  $eventSettingsRepository,
        EventStatsFetchService            $statsFetchService,
    ): void
    {
        $now = Carbon::now('UTC');

        // Find all event settings with sales report enabled
        $settingsWithReports = $eventSettingsRepository->findWhere([
            static function ($query) {
                $query->whereNotNull(EventSettingDomainObjectAbstract::SALES_REPORT_FREQUENCY);
            },
        ]);

        foreach ($settingsWithReports as $settings) {
            /** @var EventSettingDomainObject $settings */
            $frequency = $settings->getSalesReportFrequency();
            $recipients = $settings->getSalesReportRecipientEmails();

            if (!$frequency || empty($recipients)) {
                continue;
            }

            if (!$this->shouldSendNow($frequency, $now)) {
                continue;
            }

            try {
                $event = $eventRepository->findById($settings->getEventId());

                if ($event->getStatus() !== EventStatus::LIVE->name) {
                    continue;
                }

                [$startDate, $endDate, $periodLabel] = $this->getReportPeriod($frequency, $now);

                $stats = $statsFetchService->getEventStats(new EventStatsRequestDTO(
                    event_id: $event->getId(),
                    start_date: $startDate,
                    end_date: $endDate,
                ));

                $emails = is_string($recipients) ? json_decode($recipients, true) : $recipients;

                foreach ($emails as $email) {
                    Mail::to($email)->send(new SalesReportMail($event, $stats, $periodLabel));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send sales report for event ' . $settings->getEventId(), [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function shouldSendNow(string $frequency, Carbon $now): bool
    {
        return match ($frequency) {
            'DAILY' => $now->hour === 0 && $now->minute < 15,
            'WEEKLY' => $now->isMonday() && $now->hour === 0 && $now->minute < 15,
            'MONTHLY' => $now->day === 1 && $now->hour === 0 && $now->minute < 15,
            default => false,
        };
    }

    private function getReportPeriod(string $frequency, Carbon $now): array
    {
        return match ($frequency) {
            'DAILY' => [
                $now->copy()->subDay()->toDateString(),
                $now->copy()->subDay()->toDateString(),
                __('Daily Report — :date', ['date' => $now->copy()->subDay()->format('M j, Y')]),
            ],
            'WEEKLY' => [
                $now->copy()->subWeek()->startOfWeek()->toDateString(),
                $now->copy()->subWeek()->endOfWeek()->toDateString(),
                __('Weekly Report — :start to :end', [
                    'start' => $now->copy()->subWeek()->startOfWeek()->format('M j'),
                    'end' => $now->copy()->subWeek()->endOfWeek()->format('M j, Y'),
                ]),
            ],
            'MONTHLY' => [
                $now->copy()->subMonth()->startOfMonth()->toDateString(),
                $now->copy()->subMonth()->endOfMonth()->toDateString(),
                __('Monthly Report — :month', [
                    'month' => $now->copy()->subMonth()->format('F Y'),
                ]),
            ],
        };
    }
}
