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

class EventDraftStaleNudge implements LifecycleNudgeInterface
{
    private const MIN_AGE_DAYS = 3;

    public function __construct(
        private readonly AccountLifecycleNudgeRepositoryInterface $nudgeRepository,
    ) {}

    public function type(): LifecycleNudgeType
    {
        return LifecycleNudgeType::EVENT_DRAFT_STALE;
    }

    public function findCandidates(CarbonInterface $triggeredAfter, int $limit): Collection
    {
        return $this->nudgeRepository
            ->findDraftEventCandidates(
                createdAfter: $triggeredAfter,
                createdBefore: Carbon::now()->subDays(self::MIN_AGE_DAYS),
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
            subject: '{{ event.title }} is waiting to be published',
            body: <<<'HTML'
<p>Hi {{ first_name }},</p>
<p>I noticed {{ event.title }} is still a draft, so nobody can buy tickets yet.</p>
<p>When you're ready, check your tickets and event page, then publish it to start selling.</p>
<p>If something's holding you back, reply to this email and I'll help you get it live.</p>
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
                'description' => __('The draft event\'s title'),
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
