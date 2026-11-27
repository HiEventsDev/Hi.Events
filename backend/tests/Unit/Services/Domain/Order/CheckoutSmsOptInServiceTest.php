<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\CrmSmsConnectionDomainObject;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\PhoneCollection;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSmsConnectionRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use HiEvents\Services\Domain\Order\CheckoutSmsOptInService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutSmsOptInServiceTest extends TestCase
{
    private FeatureFlagService|MockInterface $featureFlagService;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private CrmSmsConnectionRepositoryInterface|MockInterface $smsConnectionRepository;

    private CheckoutSmsOptInService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->featureFlagService = Mockery::mock(FeatureFlagService::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->smsConnectionRepository = Mockery::mock(CrmSmsConnectionRepositoryInterface::class);
        $this->smsConnectionRepository->shouldReceive('findFirstWhere')->with(['organizer_id' => 3])->andReturn(new CrmSmsConnectionDomainObject)->byDefault();
        $this->service = new CheckoutSmsOptInService($this->featureFlagService, $this->eventRepository, $this->smsConnectionRepository);
    }

    public static function accountFlags(): array
    {
        return [
            'audience and text messages on' => [true, true, true],
            'text messages off' => [true, false, false],
            'audience off' => [false, true, false],
        ];
    }

    #[DataProvider('accountFlags')]
    public function test_the_opt_in_is_offered_only_when_the_account_can_send_texts(bool $audience, bool $textMessages, bool $offered): void
    {
        $this->eventRepository->shouldReceive('findById')->with(7)->andReturn((new EventDomainObject)->setAccountId(42)->setOrganizerId(3));
        $this->featureFlagService->shouldReceive('getEnabledFlagsForAccount')->with(42)->andReturn([
            FeatureFlag::CRM->value => $audience,
            FeatureFlag::CRM_SMS->value => $textMessages,
        ]);

        $this->assertSame($offered, $this->service->isOffered($this->settings(PhoneCollection::OPTIONAL, true)));
    }

    public function test_the_opt_in_is_not_offered_when_the_organizer_has_no_text_message_connection(): void
    {
        $this->eventRepository->shouldReceive('findById')->with(7)->andReturn((new EventDomainObject)->setAccountId(42)->setOrganizerId(3));
        $this->featureFlagService->shouldReceive('getEnabledFlagsForAccount')->with(42)->andReturn([
            FeatureFlag::CRM->value => true,
            FeatureFlag::CRM_SMS->value => true,
        ]);
        $this->smsConnectionRepository->shouldReceive('findFirstWhere')->with(['organizer_id' => 3])->andReturnNull();

        $this->assertFalse($this->service->isOffered($this->settings(PhoneCollection::REQUIRED, true)));
        $this->assertFalse($this->service->isRecordedFor($this->settings(PhoneCollection::REQUIRED, true), 'GB'));
    }

    public static function eventSettings(): array
    {
        return [
            'opt-in switched off' => [PhoneCollection::REQUIRED, false],
            'mobile number not asked for' => [PhoneCollection::OFF, true],
        ];
    }

    #[DataProvider('eventSettings')]
    public function test_the_opt_in_is_not_offered_when_the_event_does_not_ask_for_it(PhoneCollection $phoneCollection, bool $showSmsOptIn): void
    {
        $this->eventRepository->shouldNotReceive('findById');
        $this->featureFlagService->shouldNotReceive('getEnabledFlagsForAccount');
        $this->smsConnectionRepository->shouldNotReceive('findFirstWhere');

        $this->assertFalse($this->service->isOffered($this->settings($phoneCollection, $showSmsOptIn)));
    }

    public static function numberCountries(): array
    {
        return [
            'a country we text' => ['GB', true],
            'a country we do not text' => ['FR', false],
            'a territory that shares a supported calling code' => ['GG', false],
            'no number' => [null, false],
        ];
    }

    #[DataProvider('numberCountries')]
    public function test_an_opt_in_is_recorded_only_for_a_number_in_a_country_we_text(?string $phoneCountry, bool $recorded): void
    {
        $this->eventRepository->shouldReceive('findById')->with(7)->andReturn((new EventDomainObject)->setAccountId(42)->setOrganizerId(3));
        $this->featureFlagService->shouldReceive('getEnabledFlagsForAccount')->with(42)->andReturn([
            FeatureFlag::CRM->value => true,
            FeatureFlag::CRM_SMS->value => true,
        ]);

        $this->assertSame($recorded, $this->service->isRecordedFor($this->settings(PhoneCollection::REQUIRED, true), $phoneCountry));
    }

    public function test_an_opt_in_is_not_recorded_when_it_is_not_offered(): void
    {
        $this->assertFalse($this->service->isRecordedFor($this->settings(PhoneCollection::REQUIRED, false), 'GB'));
    }

    private function settings(PhoneCollection $phoneCollection, bool $showSmsOptIn): EventSettingDomainObject
    {
        return (new EventSettingDomainObject)
            ->setEventId(7)
            ->setPhoneCollection($phoneCollection->value)
            ->setShowSmsOptIn($showSmsOptIn);
    }
}
