<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail\Nudges;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\Helper\Url;
use HiEvents\Repository\DTO\LifecycleEmail\EventNudgeCandidateDTO;
use HiEvents\Repository\Interfaces\AccountLifecycleNudgeRepositoryInterface;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailRecipientDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailTemplateDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleNudgeCandidateDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LiveEventNoSalesNudge implements LifecycleNudgeInterface
{
    private const MIN_AGE_DAYS = 7;

    public function __construct(
        private readonly AccountLifecycleNudgeRepositoryInterface $nudgeRepository,
    ) {}

    public function type(): LifecycleNudgeType
    {
        return LifecycleNudgeType::LIVE_EVENT_NO_SALES;
    }

    public function findCandidates(CarbonInterface $triggeredAfter, int $limit): Collection
    {
        return $this->nudgeRepository
            ->findLiveEventWithoutSalesCandidates(
                readyAfter: $triggeredAfter,
                readyBefore: Carbon::now()->subDays(self::MIN_AGE_DAYS),
                upcomingAfter: Carbon::now(),
                limit: $limit,
            )
            ->map(static fn (EventNudgeCandidateDTO $candidate) => new LifecycleNudgeCandidateDTO(
                accountId: $candidate->accountId,
                email: $candidate->email,
                recipient: new LifecycleEmailRecipientDTO(
                    firstName: $candidate->firstName,
                    ctaUrl: sprintf(Url::getFrontEndUrlFromConfig(Url::EVENT_DASHBOARD), $candidate->eventId),
                    context: ['event' => ['title' => $candidate->eventTitle]],
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
            subject: 'A few ways to get your first sale for {{ event.title }}',
            body: <<<'HTML'
<p>Hi {{ first_name }},</p>
<p>{{ event.title }} is live, but no tickets have sold yet. Most first sales come from organizers sharing the event themselves, so here's what tends to work:</p>
<ol>
<li>Share your event page link wherever your audience already is: your newsletter, social profiles and group chats.</li>
<li>Embed the ticket widget on your own website so people can buy without leaving it.</li>
<li>Create a promo code for an early-bird or friends discount to give people a reason to buy now.</li>
</ol>
<p>If something on the event page isn't working the way you expected, reply and I'll take a look.</p>
HTML,
            ctaLabel: 'Open your event',
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
                'description' => __('The live event\'s title'),
                'example' => 'Summer Rooftop Sessions',
            ],
        ];
    }

    public function sampleRecipient(): LifecycleEmailRecipientDTO
    {
        return new LifecycleEmailRecipientDTO(
            firstName: 'Alex',
            ctaUrl: config('app.frontend_url'),
            context: ['event' => ['title' => 'Summer Rooftop Sessions']],
        );
    }
}
