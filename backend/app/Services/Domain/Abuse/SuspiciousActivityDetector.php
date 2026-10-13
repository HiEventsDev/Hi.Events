<?php

namespace HiEvents\Services\Domain\Abuse;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Repository\Interfaces\SuspiciousActivityRepositoryInterface;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityFindingDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Support\Collection;

class SuspiciousActivityDetector
{
    public const SUSPICIOUS_NAME_PATTERN = '(https?:|www\.|\.(com|net|org|info|biz|io|co|ly|me|app|xyz|site|online|link|shop|top|click|live|store|ru|cn|tk)\M|[<>@/{}|]|([0-9][^0-9]{0,3}){5,})';

    public const SUSPICIOUS_TITLE_PATTERN = '([0-9]{3}[ ).-]{0,2}[0-9]{3}[ .-]?[0-9]{4}|crypto|bitcoin|usdt|geek ?squad|helpline|antivirus|coinbase|binance|https?:|www\.)';

    private const SIGNUP_WINDOW_MINUTES = 15;

    private const SIGNUP_MINIMUM_SPIKE = 20;

    private const SIGNUP_BASELINE_MULTIPLIER = 5;

    private const NAME_WINDOW_MINUTES = 15;

    private const ACTIVITY_WINDOW_MINUTES = 60;

    private const INVITE_THRESHOLD = 15;

    private const UNPUBLISHED_EVENT_ATTENDEE_THRESHOLD = 20;

    private const FAILED_PAYMENT_THRESHOLD = 10;

    private const WAITLIST_THRESHOLD = 50;

    private const EMAIL_MINIMUM_VOLUME = 50;

    private const EMAIL_BOUNCE_RATE_THRESHOLD = 0.1;

    private const EMAIL_COMPLAINT_THRESHOLD = 3;

    private const EMAIL_VOLUME_THRESHOLD = 100;

    private const VOLUME_SENSITIVE_EMAIL_CATEGORIES = [
        'confirm_email_address',
        'email_confirmation_code',
        'confirm_email_change',
        'user_invited',
        'forgot_password',
        'organizer_contact',
        'waitlist_confirmation',
        'ticket_lookup',
        'unpublished_event_update',
    ];

    private const LIST_LIMIT = 10;

    public function __construct(
        private readonly SuspiciousActivityRepositoryInterface $repository,
        private readonly EventSpamCheckService $eventSpamCheckService,
    ) {}

    /**
     * @return Collection<int, SuspiciousActivityFindingDTO>
     */
    public function detect(?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();

        return collect([
            $this->detectSignupSpike($now),
            $this->detectSuspiciousNames($now),
            $this->detectInviteBursts($now),
            $this->detectUnpublishedEventOrderBursts($now),
            $this->detectFailedPaymentBursts($now),
            $this->detectWaitlistBursts($now),
            $this->detectUncheckedLiveEvents($now),
            ...$this->detectEmailProblems($now),
        ])->filter()->values();
    }

    private function detectSignupSpike(CarbonImmutable $now): ?SuspiciousActivityFindingDTO
    {
        $since = $now->subMinutes(self::SIGNUP_WINDOW_MINUTES);
        $recent = $this->repository->countAccountsCreatedBetween($since, $now);

        $baselineDays = 7;
        $baselineTotal = $this->repository->countAccountsCreatedBetween($since->subDays($baselineDays), $since);
        $baselinePerWindow = $baselineTotal / ($baselineDays * 24 * 60 / self::SIGNUP_WINDOW_MINUTES);

        if ($recent < max(self::SIGNUP_MINIMUM_SPIKE, $baselinePerWindow * self::SIGNUP_BASELINE_MULTIPLIER)) {
            return null;
        }

        $details = $this->repository
            ->findRecentAccountNamesAndEmailDomains($since, self::LIST_LIMIT)
            ->map(fn (object $row) => sprintf('%dx "%s" (@%s)', $row->total, $row->name, $row->email_domain))
            ->all();

        return new SuspiciousActivityFindingDTO(
            type: 'signup_spike',
            summary: sprintf(
                '%d accounts created in the last %d minutes (normally about %.1f).',
                $recent,
                self::SIGNUP_WINDOW_MINUTES,
                $baselinePerWindow,
            ),
            details: $details,
            fingerprint: 'signup_spike|'.$now->format('Y-m-d-H'),
        );
    }

    private function detectSuspiciousNames(CarbonImmutable $now): ?SuspiciousActivityFindingDTO
    {
        $since = $now->subMinutes(self::NAME_WINDOW_MINUTES);

        $rows = collect([
            'user' => $this->repository->findUsersWithSuspiciousNames($since, self::SUSPICIOUS_NAME_PATTERN, self::LIST_LIMIT),
            'account' => $this->repository->findAccountsWithSuspiciousNames($since, self::SUSPICIOUS_NAME_PATTERN, self::LIST_LIMIT),
            'organizer' => $this->repository->findOrganizersWithSuspiciousNames($since, self::SUSPICIOUS_TITLE_PATTERN, self::LIST_LIMIT),
            'event' => $this->repository->findEventsWithSuspiciousTitles($since, self::SUSPICIOUS_TITLE_PATTERN, self::LIST_LIMIT),
        ])->flatMap(fn (Collection $matches, string $kind) => $matches->map(fn (object $row) => [
            'key' => $kind.':'.$row->id,
            'line' => sprintf('%s %d: "%s"', $kind, $row->id, $row->name),
        ]));

        if ($rows->isEmpty()) {
            return null;
        }

        return new SuspiciousActivityFindingDTO(
            type: 'suspicious_names',
            summary: sprintf('%d recently created or edited records contain URLs, phone numbers or scam keywords.', $rows->count()),
            details: $rows->pluck('line')->all(),
            fingerprint: 'suspicious_names|'.$rows->pluck('key')->sort()->implode(','),
        );
    }

    private function detectInviteBursts(CarbonImmutable $now): ?SuspiciousActivityFindingDTO
    {
        $rows = $this->repository->findAccountsWithInviteBursts(
            $now->subMinutes(self::ACTIVITY_WINDOW_MINUTES),
            self::INVITE_THRESHOLD,
        );

        if ($rows->isEmpty()) {
            return null;
        }

        return new SuspiciousActivityFindingDTO(
            type: 'invite_burst',
            summary: sprintf('Accounts sent %d or more team invitations in the last hour.', self::INVITE_THRESHOLD),
            details: $rows->map(fn (object $row) => sprintf('account %d: %d invitations', $row->account_id, $row->total))->all(),
            fingerprint: 'invite_burst|'.$rows->pluck('account_id')->sort()->implode(',').'|'.$now->format('Y-m-d-H'),
        );
    }

    private function detectUnpublishedEventOrderBursts(CarbonImmutable $now): ?SuspiciousActivityFindingDTO
    {
        $rows = $this->repository->findUnpublishedEventsWithOrderBursts(
            $now->subMinutes(self::ACTIVITY_WINDOW_MINUTES),
            [EventStatus::DRAFT->name, EventStatus::PENDING_MANUAL_REVIEW->name, EventStatus::ARCHIVED->name],
            self::UNPUBLISHED_EVENT_ATTENDEE_THRESHOLD,
        );

        if ($rows->isEmpty()) {
            return null;
        }

        return new SuspiciousActivityFindingDTO(
            type: 'unpublished_event_attendees',
            summary: 'Attendees are being created in bulk on events that are not live.',
            details: $rows->map(fn (object $row) => sprintf(
                'event %d (account %d, %s): %d attendees, %d distinct emails, %d orders',
                $row->event_id,
                $row->account_id,
                $row->status,
                $row->attendees,
                $row->distinct_emails,
                $row->orders,
            ))->all(),
            fingerprint: 'unpublished_event_attendees|'.$rows->pluck('event_id')->sort()->implode(',').'|'.$now->format('Y-m-d-H'),
        );
    }

    private function detectFailedPaymentBursts(CarbonImmutable $now): ?SuspiciousActivityFindingDTO
    {
        $rows = $this->repository->findEventsWithFailedPaymentBursts(
            $now->subMinutes(self::ACTIVITY_WINDOW_MINUTES),
            self::FAILED_PAYMENT_THRESHOLD,
        );

        if ($rows->isEmpty()) {
            return null;
        }

        return new SuspiciousActivityFindingDTO(
            type: 'failed_payment_burst',
            summary: 'Possible card testing: many failed payments on one event in the last hour.',
            details: $rows->map(fn (object $row) => sprintf('event %d: %d orders with failed payments', $row->event_id, $row->total))->all(),
            fingerprint: 'failed_payment_burst|'.$rows->pluck('event_id')->sort()->implode(',').'|'.$now->format('Y-m-d-H'),
        );
    }

    private function detectWaitlistBursts(CarbonImmutable $now): ?SuspiciousActivityFindingDTO
    {
        $rows = $this->repository->findEventsWithWaitlistBursts(
            $now->subMinutes(self::ACTIVITY_WINDOW_MINUTES),
            self::WAITLIST_THRESHOLD,
        );

        if ($rows->isEmpty()) {
            return null;
        }

        return new SuspiciousActivityFindingDTO(
            type: 'waitlist_burst',
            summary: 'Unusually many waitlist sign-ups on one event in the last hour.',
            details: $rows->map(fn (object $row) => sprintf('event %d: %d waitlist entries', $row->event_id, $row->total))->all(),
            fingerprint: 'waitlist_burst|'.$rows->pluck('event_id')->sort()->implode(',').'|'.$now->format('Y-m-d-H'),
        );
    }

    private function detectUncheckedLiveEvents(CarbonImmutable $now): ?SuspiciousActivityFindingDTO
    {
        if (! $this->eventSpamCheckService->isEnabled()) {
            return null;
        }

        $rows = $this->repository->findLiveEventsWithoutSpamCheck(
            $now->subDay(),
            $now->subMinutes(30),
            self::LIST_LIMIT,
        );

        if ($rows->isEmpty()) {
            return null;
        }

        return new SuspiciousActivityFindingDTO(
            type: 'unchecked_live_events',
            summary: 'Live events have no spam check result after 30 minutes. The spam check job may be failing.',
            details: $rows->map(fn (object $row) => sprintf('event %d (account %d): "%s"', $row->id, $row->account_id, $row->title))->all(),
            fingerprint: 'unchecked_live_events|'.$rows->pluck('id')->sort()->implode(','),
        );
    }

    /**
     * @return array<int, SuspiciousActivityFindingDTO>
     */
    private function detectEmailProblems(CarbonImmutable $now): array
    {
        $stats = $this->repository->findEmailCategoryStats($now->subMinutes(self::ACTIVITY_WINDOW_MINUTES));
        $hour = $now->format('Y-m-d-H');

        $deliveryProblems = $stats->filter(fn (object $row) => $row->complained >= self::EMAIL_COMPLAINT_THRESHOLD
            || ($row->total >= self::EMAIL_MINIMUM_VOLUME && $row->bounced / $row->total >= self::EMAIL_BOUNCE_RATE_THRESHOLD));

        $volumeSpikes = $stats->filter(fn (object $row) => $row->total >= self::EMAIL_VOLUME_THRESHOLD
            && in_array($row->category, self::VOLUME_SENSITIVE_EMAIL_CATEGORIES, true));

        return array_values(array_filter([
            $deliveryProblems->isEmpty() ? null : new SuspiciousActivityFindingDTO(
                type: 'email_bounces_or_complaints',
                summary: 'High bounce or complaint numbers in the last hour.',
                details: $deliveryProblems->map(fn (object $row) => sprintf(
                    '%s: %d sent, %d bounced, %d complaints',
                    $row->category,
                    $row->total,
                    $row->bounced,
                    $row->complained,
                ))->values()->all(),
                fingerprint: 'email_bounces_or_complaints|'.$deliveryProblems->pluck('category')->sort()->implode(',').'|'.$hour,
            ),
            $volumeSpikes->isEmpty() ? null : new SuspiciousActivityFindingDTO(
                type: 'email_volume_spike',
                summary: sprintf('%d or more account, invite or contact emails of one type sent in the last hour.', self::EMAIL_VOLUME_THRESHOLD),
                details: $volumeSpikes->map(fn (object $row) => sprintf('%s: %d sent', $row->category, $row->total))->values()->all(),
                fingerprint: 'email_volume_spike|'.$volumeSpikes->pluck('category')->sort()->implode(',').'|'.$hour,
            ),
        ]));
    }
}
