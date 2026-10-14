<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail\Nudges;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\Helper\Url;
use HiEvents\Repository\DTO\LifecycleEmail\AccountOwnerNudgeCandidateDTO;
use HiEvents\Repository\Interfaces\AccountLifecycleNudgeRepositoryInterface;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailRecipientDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailTemplateDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleNudgeCandidateDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NoEventCreatedNudge implements LifecycleNudgeInterface
{
    private const MIN_AGE_DAYS = 3;

    public function __construct(
        private readonly AccountLifecycleNudgeRepositoryInterface $nudgeRepository,
    ) {}

    public function type(): LifecycleNudgeType
    {
        return LifecycleNudgeType::NO_EVENT_CREATED;
    }

    public function findCandidates(CarbonInterface $triggeredAfter, int $limit): Collection
    {
        return $this->nudgeRepository
            ->findNoEventCandidates(
                createdAfter: $triggeredAfter,
                createdBefore: Carbon::now()->subDays(self::MIN_AGE_DAYS),
                limit: $limit,
            )
            ->map(static fn (AccountOwnerNudgeCandidateDTO $candidate) => new LifecycleNudgeCandidateDTO(
                accountId: $candidate->accountId,
                email: $candidate->email,
                recipient: new LifecycleEmailRecipientDTO(
                    firstName: $candidate->firstName,
                    ctaUrl: Url::getFrontEndUrlFromConfig(Url::WELCOME),
                    accountId: $candidate->accountId,
                    userId: $candidate->userId,
                ),
            ));
    }

    public function defaultTemplate(): LifecycleEmailTemplateDTO
    {
        return new LifecycleEmailTemplateDTO(
            nudgeType: $this->type(),
            subject: 'Your first event is a few minutes away',
            body: <<<'HTML'
<p>Hi {{ first_name }},</p>
<p>I'm Dave, I run Hi.Events. I noticed you signed up but haven't created an event yet, so here's the short version of how it works:</p>
<ol>
<li>Create your event and add a date, a location and a description.</li>
<li>Add your tickets. Free tickets work straight away, and for paid tickets you connect Stripe to get paid.</li>
<li>Publish it and share your event page, or embed the ticket widget on your own website.</li>
</ol>
<p>If you're not sure Hi.Events fits what you're planning, just reply and tell me about it.</p>
HTML,
            ctaLabel: 'Create your first event',
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
        ];
    }

    public function sampleRecipient(): LifecycleEmailRecipientDTO
    {
        return new LifecycleEmailRecipientDTO(
            firstName: 'Alex',
            ctaUrl: config('app.frontend_url'),
        );
    }
}
