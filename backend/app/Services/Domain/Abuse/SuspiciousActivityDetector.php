<?php

namespace HiEvents\Services\Domain\Abuse;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Repository\Interfaces\SuspiciousActivityRepositoryInterface;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityAccountDTO;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityFindingDTO;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityItemDTO;
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

        $findings = collect([
            $this->detectSignupSpike($now),
            $this->detectSuspiciousNames($now),
            $this->detectInviteBursts($now),
            $this->detectUnpublishedEventOrderBursts($now),
            $this->detectFailedPaymentBursts($now),
            $this->detectWaitlistBursts($now),
            $this->detectUncheckedLiveEvents($now),
            ...$this->detectEmailProblems($now),
        ])->filter()->values();

        return $this->attachAccounts($findings);
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

        $items = $this->repository
            ->findRecentAccountNamesAndEmailDomains($since, self::LIST_LIMIT)
            ->map(fn (object $row) => new SuspiciousActivityItemDTO(
                label: sprintf('%dx "%s" (@%s)', $row->total, $row->name, $row->email_domain),
                accountSearch: $row->name,
            ))
            ->all();

        return new SuspiciousActivityFindingDTO(
            type: 'signup_spike',
            summary: sprintf(
                '%d accounts created in the last %d minutes (normally about %.1f).',
                $recent,
                self::SIGNUP_WINDOW_MINUTES,
                $baselinePerWindow,
            ),
            items: $items,
            fingerprint: 'signup_spike|'.$now->format('Y-m-d-H'),
            nextStep: 'Repeated names or one email domain usually mean scripted sign-ups. Open the matching accounts and suspend the automated ones.',
            adminPath: 'accounts',
            adminLinkLabel: 'Open accounts',
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
            'item' => new SuspiciousActivityItemDTO(
                label: sprintf('%s %d: "%s"', ucfirst($kind), $row->id, $row->name),
                accountId: $this->accountId($row),
            ),
        ]));

        if ($rows->isEmpty()) {
            return null;
        }

        return new SuspiciousActivityFindingDTO(
            type: 'suspicious_names',
            summary: sprintf('%d recently created or edited records contain URLs, phone numbers or scam keywords.', $rows->count()),
            items: $rows->pluck('item')->all(),
            fingerprint: 'suspicious_names|'.$rows->pluck('key')->sort()->implode(','),
            nextStep: 'Scam pages put phone numbers, crypto terms or links in names and titles. Suspend the account if the name is an advert or a scam; leave it if it is a real business name.',
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
            items: $rows->map(fn (object $row) => new SuspiciousActivityItemDTO(
                label: sprintf('%d invitations', $row->total),
                accountId: (int) $row->account_id,
            ))->all(),
            fingerprint: 'invite_burst|'.$rows->pluck('account_id')->sort()->implode(',').'|'.$now->format('Y-m-d-H'),
            nextStep: 'Each invitation emails an address the account typed in, so bursts are a common spam route. Check the members list and suspend the account if the invitees look like a mailing list.',
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
            items: $rows->map(fn (object $row) => new SuspiciousActivityItemDTO(
                label: $this->eventLabel($row->event_id, $row->title),
                facts: [
                    $row->status,
                    sprintf('%d attendees, %d distinct emails, %d orders', $row->attendees, $row->distinct_emails, $row->orders),
                ],
                accountId: (int) $row->account_id,
            ))->all(),
            fingerprint: 'unpublished_event_attendees|'.$rows->pluck('event_id')->sort()->implode(',').'|'.$now->format('Y-m-d-H'),
            nextStep: 'Orders on an event that is not live usually mean ticket emails are being used to reach a list of addresses. Suspend the account if the attendee emails do not look like real buyers.',
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
            items: $rows->map(fn (object $row) => new SuspiciousActivityItemDTO(
                label: $this->eventLabel($row->event_id, $row->title),
                facts: [$row->status, sprintf('%d orders with failed payments', $row->total)],
                accountId: (int) $row->account_id,
            ))->all(),
            fingerprint: 'failed_payment_burst|'.$rows->pluck('event_id')->sort()->implode(',').'|'.$now->format('Y-m-d-H'),
            nextStep: 'Check the declines in the Stripe dashboard. If they come from many cards in quick succession, suspend the account to take the event offline.',
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
            items: $rows->map(fn (object $row) => new SuspiciousActivityItemDTO(
                label: $this->eventLabel($row->event_id, $row->title),
                facts: [$row->status, sprintf('%d waitlist entries', $row->total)],
                accountId: (int) $row->account_id,
            ))->all(),
            fingerprint: 'waitlist_burst|'.$rows->pluck('event_id')->sort()->implode(',').'|'.$now->format('Y-m-d-H'),
            nextStep: 'Every waitlist sign-up sends a confirmation email. A popular event can do this legitimately; suspend the account only if the entries look scripted.',
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
            items: $rows->map(fn (object $row) => new SuspiciousActivityItemDTO(
                label: $this->eventLabel($row->id, $row->title),
                accountId: (int) $row->account_id,
            ))->all(),
            fingerprint: 'unchecked_live_events|'.$rows->pluck('id')->sort()->implode(','),
            nextStep: 'Look for failed EventSpamCheckJob runs and check the queue worker is running. These events are live without having been reviewed.',
            adminPath: 'failed-jobs',
            adminLinkLabel: 'Open failed jobs',
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
                items: $deliveryProblems->map(fn (object $row) => new SuspiciousActivityItemDTO(
                    label: $row->category,
                    facts: [sprintf('%d sent, %d bounced, %d complaints', $row->total, $row->bounced, $row->complained)],
                ))->values()->all(),
                fingerprint: 'email_bounces_or_complaints|'.$deliveryProblems->pluck('category')->sort()->implode(',').'|'.$hour,
                nextStep: 'Bounces and complaints hurt sending reputation for every account. Find the recipients in email delivery and trace them back to the account that triggered the emails.',
                adminPath: 'email-delivery',
                adminLinkLabel: 'Open email delivery',
            ),
            $volumeSpikes->isEmpty() ? null : new SuspiciousActivityFindingDTO(
                type: 'email_volume_spike',
                summary: sprintf('%d or more account, invite or contact emails of one type sent in the last hour.', self::EMAIL_VOLUME_THRESHOLD),
                items: $volumeSpikes->map(fn (object $row) => new SuspiciousActivityItemDTO(
                    label: $row->category,
                    facts: [sprintf('%d sent', $row->total)],
                ))->values()->all(),
                fingerprint: 'email_volume_spike|'.$volumeSpikes->pluck('category')->sort()->implode(',').'|'.$hour,
                nextStep: 'These emails go to addresses a visitor typed in, so a spike usually means a form is being abused. Find the recipients in email delivery and check for a matching signup, invite or waitlist finding.',
                adminPath: 'email-delivery',
                adminLinkLabel: 'Open email delivery',
            ),
        ]));
    }

    /**
     * @param  Collection<int, SuspiciousActivityFindingDTO>  $findings
     * @return Collection<int, SuspiciousActivityFindingDTO>
     */
    private function attachAccounts(Collection $findings): Collection
    {
        $accountIds = $findings
            ->flatMap(fn (SuspiciousActivityFindingDTO $finding) => $finding->items)
            ->map(fn (SuspiciousActivityItemDTO $item) => $item->accountId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($accountIds === []) {
            return $findings;
        }

        $accounts = $this->repository->findAccountContexts($accountIds)
            ->map(fn (object $row) => new SuspiciousActivityAccountDTO(
                id: (int) $row->id,
                name: $row->name,
                email: $row->email,
                createdAt: CarbonImmutable::parse($row->created_at),
                isVerified: (bool) $row->is_manually_verified,
                hasStripe: (bool) $row->has_stripe,
                liveEvents: (int) $row->live_events,
                isSuspended: $row->suspended_at !== null,
            ));

        return $findings->map(fn (SuspiciousActivityFindingDTO $finding) => $finding->withItems(array_map(
            fn (SuspiciousActivityItemDTO $item) => $item->withAccount($accounts->get($item->accountId)),
            $finding->items,
        )));
    }

    private function eventLabel(int|string $eventId, ?string $title): string
    {
        return sprintf('Event %d: "%s"', $eventId, $title);
    }

    private function accountId(object $row): ?int
    {
        return isset($row->account_id) ? (int) $row->account_id : null;
    }
}
