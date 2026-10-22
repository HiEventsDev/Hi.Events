<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Event\UnpublishedEventUpdateMail;
use HiEvents\Mail\Order\OrderSummary;
use HiEvents\Mail\Organizer\OrderSummaryForOrganizer;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Mail\EmailAccentColorService;
use HiEvents\Services\Domain\Mail\MailBrandingService;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionAccessService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MailBrandingServiceTest extends TestCase
{
    private AccountSubscriptionAccessService|MockInterface $accessService;

    private OrganizerRepositoryInterface|MockInterface $organizerRepository;

    private EventSettingsRepositoryInterface|MockInterface $eventSettingsRepository;

    private MailBrandingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accessService = Mockery::mock(AccountSubscriptionAccessService::class);
        $this->organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        config(['app.cdn_url' => 'https://cdn.test', 'app.frontend_url' => 'https://tickets.example.test', 'app.name' => 'Hi.Events']);

        $this->accessService->shouldReceive('areSubscriptionsAvailable')->andReturnTrue()->byDefault();

        $this->eventSettingsRepository = Mockery::mock(EventSettingsRepositoryInterface::class);
        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturnNull()->byDefault();

        $this->service = new MailBrandingService(
            $this->accessService,
            $this->organizerRepository,
            $this->eventSettingsRepository,
            new EmailAccentColorService,
        );
    }

    public function test_order_and_ticket_mail_carry_the_promo_for_unsubscribed_accounts(): void
    {
        $this->givenSubscription(false);

        $branding = $this->service->forMail($this->orderSummary());

        $this->assertTrue($branding->showHiEventsPromo);
        $this->assertFalse($branding->hideHiEventsBranding);
        $this->assertNull($branding->organizerName);
        $this->assertSame(
            'https://tickets.example.test/auth/register?utm_source=email-promo&utm_medium=email&utm_campaign=create-your-event',
            $branding->promoUrl,
        );
    }

    public function test_other_event_mail_has_no_promo(): void
    {
        $this->givenSubscription(false);

        $branding = $this->service->forMail(new OrderSummaryForOrganizer($this->order(), $this->event()));

        $this->assertFalse($branding->showHiEventsPromo);
    }

    public function test_account_mail_has_no_promo_and_never_checks_a_subscription(): void
    {
        $this->accessService->shouldNotReceive('hasActiveSubscription');

        $branding = $this->service->forMail(new UnpublishedEventUpdateMail);

        $this->assertFalse($branding->showHiEventsPromo);
        $this->assertFalse($branding->hideHiEventsBranding);
    }

    public function test_a_subscriber_whose_organizer_is_gone_keeps_the_default_header_without_the_promo(): void
    {
        $this->givenSubscription(true);
        $this->organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->organizerRepository->shouldReceive('findFirstWhere')->andReturnNull();

        $branding = $this->service->forMail($this->orderSummary());

        $this->assertFalse($branding->hideHiEventsBranding);
        $this->assertFalse($branding->showHiEventsPromo);
        $this->assertNull($branding->organizerName);
    }

    public function test_installs_other_than_hi_events_never_show_the_promo_or_check_subscriptions(): void
    {
        $this->accessService->shouldReceive('areSubscriptionsAvailable')->andReturnFalse();
        $this->accessService->shouldNotReceive('hasActiveSubscription');

        $branding = $this->service->forMail($this->orderSummary());

        $this->assertFalse($branding->showHiEventsPromo);
        $this->assertFalse($branding->hideHiEventsBranding);
    }

    public function test_subscribed_accounts_get_the_organizer_branding_and_no_promo(): void
    {
        $this->givenSubscription(true);

        $this->organizerRepository->shouldReceive('loadRelation')->with(ImageDomainObject::class)->andReturnSelf();
        $this->organizerRepository->shouldReceive('findFirstWhere')
            ->with(['id' => 4, 'account_id' => 7])
            ->andReturn((new OrganizerDomainObject)
                ->setId(4)
                ->setName('Acme Events')
                ->setWebsite('https://acme.test')
                ->setImages(collect([
                    (new ImageDomainObject)->setType(ImageType::ORGANIZER_COVER->name)->setPath('cover.jpg'),
                    (new ImageDomainObject)->setType(ImageType::ORGANIZER_LOGO->name)->setPath('logo.png'),
                ])));

        $branding = $this->service->forMail($this->orderSummary());

        $this->assertTrue($branding->hideHiEventsBranding);
        $this->assertFalse($branding->showHiEventsPromo);
        $this->assertSame('Acme Events', $branding->organizerName);
        $this->assertSame('https://cdn.test/logo.png', $branding->organizerLogoUrl);
        $this->assertSame('https://acme.test', $branding->organizerUrl);
    }

    public function test_buyer_emails_from_subscribers_use_the_organizer_name_and_event_accent(): void
    {
        $this->givenSubscription(true);
        $this->givenOrganizer("Acme\r\nEvents");
        $this->eventSettingsRepository->shouldReceive('findFirstWhere')
            ->with(['event_id' => 10])
            ->andReturn((new EventSettingDomainObject)->setHomepageThemeSettings(['accent' => '#1B36F5']));

        $branding = $this->service->forMail($this->orderSummary());

        $this->assertSame('Acme Events', $branding->senderName);
        $this->assertSame('#1b36f5', $branding->buttonColor);
        $this->assertSame('#ffffff', $branding->buttonTextColor);
        $this->assertSame('#1b36f5', $branding->linkColor);
    }

    public function test_the_organizers_own_order_notification_keeps_the_default_sender(): void
    {
        $this->givenSubscription(true);
        $this->givenOrganizer('Acme Events');

        $branding = $this->service->forMail(new OrderSummaryForOrganizer($this->order(), $this->event()));

        $this->assertTrue($branding->hideHiEventsBranding);
        $this->assertNull($branding->senderName);
    }

    public function test_events_without_an_accent_keep_the_default_colours(): void
    {
        $this->givenSubscription(true);
        $this->givenOrganizer('Acme Events');

        $branding = $this->service->forMail($this->orderSummary());

        $this->assertNull($branding->buttonColor);
        $this->assertNull($branding->linkColor);
    }

    #[DataProvider('impersonatingNames')]
    public function test_names_that_could_impersonate_a_sender_fall_back_to_the_default(string $name): void
    {
        $this->givenSubscription(true);
        $this->givenOrganizer($name);

        $branding = $this->service->forMail($this->orderSummary());

        $this->assertTrue($branding->hideHiEventsBranding);
        $this->assertNull($branding->senderName);
    }

    public static function impersonatingNames(): array
    {
        return [
            'email address' => ['support@paypal.com'],
            'platform name' => ['Hi.Events Support'],
            'platform name respelled' => ['hi events tickets'],
            'bidi override' => ["Acme\u{202E}stnevE"],
            'nothing left after cleaning' => ["\r\n\t"],
        ];
    }

    public function test_long_sender_names_are_truncated(): void
    {
        $this->givenSubscription(true);
        $this->givenOrganizer(str_repeat('a', 100));

        $this->assertSame(70, mb_strlen($this->service->forMail($this->orderSummary())->senderName));
    }

    private function givenOrganizer(string $name): void
    {
        $this->organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->organizerRepository->shouldReceive('findFirstWhere')
            ->andReturn((new OrganizerDomainObject)->setId(4)->setName($name)->setImages(collect()));
    }

    public function test_subscribed_organizers_without_a_logo_fall_back_to_their_name(): void
    {
        $this->givenSubscription(true);

        $this->organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->organizerRepository->shouldReceive('findFirstWhere')
            ->andReturn((new OrganizerDomainObject)->setId(4)->setName('Acme Events')->setImages(collect()));

        $branding = $this->service->forMail($this->orderSummary());

        $this->assertSame('Acme Events', $branding->organizerName);
        $this->assertNull($branding->organizerLogoUrl);
    }

    private function givenSubscription(bool $active): void
    {
        $this->accessService->shouldReceive('hasActiveSubscription')->with(7)->andReturn($active);
    }

    private function orderSummary(): OrderSummary
    {
        return new OrderSummary(
            order: $this->order(),
            event: $this->event(),
            organizer: (new OrganizerDomainObject)->setId(4)->setName('Acme Events'),
            eventSettings: new EventSettingDomainObject,
            invoice: null,
        );
    }

    private function event(): EventDomainObject
    {
        return (new EventDomainObject)->setId(10)->setAccountId(7)->setOrganizerId(4)->setTitle('Jazz Nights');
    }

    private function order(): OrderDomainObject
    {
        return (new OrderDomainObject)->setId(30)->setTotalGross(0);
    }
}
