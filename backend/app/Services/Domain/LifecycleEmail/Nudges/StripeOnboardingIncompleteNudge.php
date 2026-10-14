<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail\Nudges;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\Helper\Url;
use HiEvents\Repository\DTO\LifecycleEmail\StripeOnboardingNudgeCandidateDTO;
use HiEvents\Repository\Interfaces\AccountLifecycleNudgeRepositoryInterface;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailRecipientDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailTemplateDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleNudgeCandidateDTO;
use HiEvents\Services\Domain\Payment\Stripe\StripeConnectTimezones;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StripeOnboardingIncompleteNudge implements LifecycleNudgeInterface
{
    private const MIN_AGE_HOURS = 24;

    public function __construct(
        private readonly AccountLifecycleNudgeRepositoryInterface $nudgeRepository,
        private readonly StripeConnectTimezones $stripeConnectTimezones,
    ) {}

    public function type(): LifecycleNudgeType
    {
        return LifecycleNudgeType::STRIPE_ONBOARDING_INCOMPLETE;
    }

    public function findCandidates(CarbonInterface $triggeredAfter, int $limit): Collection
    {
        return $this->nudgeRepository
            ->findStripeOnboardingCandidates(
                triggeredAfter: $triggeredAfter,
                triggeredBefore: Carbon::now()->subHours(self::MIN_AGE_HOURS),
                excludedTimezones: $this->stripeConnectTimezones->unsupported(),
                limit: $limit,
            )
            ->map(static fn (StripeOnboardingNudgeCandidateDTO $candidate) => new LifecycleNudgeCandidateDTO(
                accountId: $candidate->accountId,
                email: $candidate->email,
                recipient: new LifecycleEmailRecipientDTO(
                    firstName: $candidate->firstName,
                    ctaUrl: sprintf(Url::getFrontEndUrlFromConfig(Url::STRIPE_CONNECT_RETURN_URL), $candidate->organizerId),
                    context: ['organizer' => ['name' => $candidate->organizerName]],
                    accountId: $candidate->accountId,
                    userId: $candidate->userId,
                ),
            ));
    }

    public function defaultTemplate(): LifecycleEmailTemplateDTO
    {
        return new LifecycleEmailTemplateDTO(
            nudgeType: $this->type(),
            subject: 'Connect Stripe to start selling paid tickets',
            body: <<<'HTML'
<p>Hi {{ first_name }},</p>
<p>{{ organizer.name }} has paid tickets, but Stripe isn't connected yet, so people can't pay by card and you can't receive payouts.</p>
<p>It takes about five minutes. Stripe asks for your business details and a bank account for payouts, and if you already started you'll pick up where you left off.</p>
<p>If something got in the way, reply to this email and I'll help.</p>
HTML,
            ctaLabel: 'Connect Stripe',
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
                'token' => '{{ organizer.name }}',
                'description' => __('The organizer that needs to connect Stripe'),
                'example' => 'Rooftop Sessions',
            ],
        ];
    }

    public function sampleRecipient(): LifecycleEmailRecipientDTO
    {
        return new LifecycleEmailRecipientDTO(
            firstName: 'Alex',
            ctaUrl: config('app.frontend_url'),
            context: ['organizer' => ['name' => 'Rooftop Sessions']],
        );
    }
}
