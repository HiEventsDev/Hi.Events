<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Event\ShareSuggestion;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\ShareSuggestionAvailability;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionAvailabilityService;
use Illuminate\Config\Repository;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventShareSuggestionAvailabilityServiceTest extends TestCase
{
    private const int EVENT_ID = 10;

    private const int ACCOUNT_ID = 20;

    private const int ORGANIZER_ID = 30;

    public static function accounts(): array
    {
        return [
            'no anthropic key' => [null, true, true, true, true, EventStatus::LIVE, ShareSuggestionAvailability::NOT_CONFIGURED],
            'email not confirmed' => ['key', true, false, true, true, EventStatus::LIVE, ShareSuggestionAvailability::EMAIL_NOT_CONFIRMED],
            'event held for review' => ['key', true, true, true, true, EventStatus::PENDING_MANUAL_REVIEW, ShareSuggestionAvailability::EVENT_UNDER_REVIEW],
            'cloud account with neither verification nor payments' => ['key', true, true, false, false, EventStatus::LIVE, ShareSuggestionAvailability::PAYMENTS_NOT_CONNECTED],
            'cloud account with payments connected' => ['key', true, true, false, true, EventStatus::DRAFT, ShareSuggestionAvailability::AVAILABLE],
            'verified cloud account without payments' => ['key', true, true, true, false, EventStatus::LIVE, ShareSuggestionAvailability::AVAILABLE],
            'self-hosted without payments' => ['key', false, true, false, false, EventStatus::LIVE, ShareSuggestionAvailability::AVAILABLE],
        ];
    }

    #[DataProvider('accounts')]
    public function test_suggestions_are_only_available_to_trusted_accounts(
        ?string $anthropicKey,
        bool $isSaas,
        bool $isEmailConfirmed,
        bool $isManuallyVerified,
        bool $isStripeConnected,
        EventStatus $eventStatus,
        ShareSuggestionAvailability $expected,
    ): void {
        $account = (new AccountDomainObject)
            ->setAccountVerifiedAt($isEmailConfirmed ? '2026-01-01 00:00:00' : null)
            ->setIsManuallyVerified($isManuallyVerified);

        $event = (new EventDomainObject)
            ->setId(self::EVENT_ID)
            ->setOrganizerId(self::ORGANIZER_ID)
            ->setStatus($eventStatus->name);

        $organizer = (new OrganizerDomainObject)->setOrganizerStripePlatforms(collect($isStripeConnected
            ? [(new OrganizerStripePlatformDomainObject)->setStripeSetupCompletedAt('2026-01-02 00:00:00')->setCreatedAt('2026-01-02 00:00:00')]
            : []));

        $accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $accountRepository->allows('findById')->with(self::ACCOUNT_ID)->andReturn($account);

        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->allows('findById')->with(self::EVENT_ID)->andReturn($event);

        $organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        $organizerRepository->allows('loadRelation')->with(OrganizerStripePlatformDomainObject::class)->andReturnSelf();
        $organizerRepository->allows('findById')->with(self::ORGANIZER_ID)->andReturn($organizer);

        $service = new EventShareSuggestionAvailabilityService(
            new Repository([
                'app' => ['saas_mode_enabled' => $isSaas],
                'ai' => ['providers' => ['anthropic' => ['key' => $anthropicKey]]],
            ]),
            $accountRepository,
            $eventRepository,
            $organizerRepository,
        );

        $this->assertSame($expected, $service->resolve(self::EVENT_ID, self::ACCOUNT_ID));
    }
}
