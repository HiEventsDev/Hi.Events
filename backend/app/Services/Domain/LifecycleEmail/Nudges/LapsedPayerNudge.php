<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail\Nudges;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\Helper\Url;
use HiEvents\Repository\DTO\LifecycleEmail\LapsedPayerNudgeCandidateDTO;
use HiEvents\Repository\Interfaces\AccountLifecycleNudgeRepositoryInterface;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailRecipientDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailTemplateDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleNudgeCandidateDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LapsedPayerNudge implements LifecycleNudgeInterface
{
    private const LAPSED_FOR_DAYS = 60;

    private const LOOKBACK_MONTHS = 18;

    public function __construct(
        private readonly AccountLifecycleNudgeRepositoryInterface $nudgeRepository,
    ) {}

    public function type(): LifecycleNudgeType
    {
        return LifecycleNudgeType::LAPSED_PAYER;
    }

    public function findCandidates(CarbonInterface $triggeredAfter, int $limit): Collection
    {
        return $this->nudgeRepository
            ->findLapsedPayerCandidates(
                lapsedAfter: Carbon::now()->subMonths(self::LOOKBACK_MONTHS),
                lapsedBefore: Carbon::now()->subDays(self::LAPSED_FOR_DAYS),
                upcomingAfter: Carbon::now(),
                limit: $limit,
            )
            ->map(static fn (LapsedPayerNudgeCandidateDTO $candidate) => new LifecycleNudgeCandidateDTO(
                accountId: $candidate->accountId,
                email: $candidate->email,
                recipient: new LifecycleEmailRecipientDTO(
                    firstName: $candidate->firstName,
                    ctaUrl: sprintf(
                        Url::getFrontEndUrlFromConfig(Url::ORGANIZER_EVENTS),
                        $candidate->organizerId,
                        $candidate->isEventArchived ? 'archived' : 'ended',
                    ),
                    context: [
                        'event' => ['title' => $candidate->eventTitle],
                        'months_since_last_event' => (int) Carbon::parse($candidate->lastSoldDateEndedAt)->diffInMonths(Carbon::now()),
                    ],
                    accountId: $candidate->accountId,
                    userId: $candidate->userId,
                ),
                eventId: $candidate->eventId,
            ));
    }

    public function defaultTemplate(): LifecycleEmailTemplateDTO
    {
        return new LifecycleEmailTemplateDTO(
            nudgeType: $this->type(),
            subject: 'Planning your next event?',
            body: <<<'HTML'
<p>Hi {{ first_name }},</p>
<p>I hope your last event went well. It's been a little while, so I wanted to ask: are you planning another one?</p>
<p>If you are, you don't have to start from scratch. Copy your last event from your past events page and change the dates, and your tickets and settings come with it.</p>
{% if months_since_last_event >= 3 %}
<p>We've also added a few things since then to make things easier, like a box office for selling on the door, checkout add-ons, and faster check-in. Reserved seating is also available. Just let me know and I'll switch it on for you.</p>
{% endif %}
<p>If anything got in the way last time, just reply and let me know.</p>
HTML,
            ctaLabel: 'See your past events',
        );
    }

    public function tokens(): array
    {
        return [
            [
                'token' => '{{ first_name }}',
                'description' => __('The account owner\'s first name'),
                'example' => 'Alex',
            ],
            [
                'token' => '{{ event.title }}',
                'description' => __('The title of the event with the account\'s most recent paid sale'),
                'example' => 'Summer Rooftop Sessions',
            ],
            [
                'token' => '{{ months_since_last_event }}',
                'description' => __('Whole months since that event\'s last sold date ended'),
                'example' => '4',
            ],
        ];
    }

    public function sampleRecipient(): LifecycleEmailRecipientDTO
    {
        return new LifecycleEmailRecipientDTO(
            firstName: 'Alex',
            ctaUrl: config('app.frontend_url'),
            context: [
                'event' => ['title' => 'Summer Rooftop Sessions'],
                'months_since_last_event' => 4,
            ],
        );
    }
}
