<?php

namespace Tests\Unit\Services\Domain\Email;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Email\EmailTemplateService;
use HiEvents\Services\Domain\Email\EmailTokenContextBuilder;
use HiEvents\Services\Domain\Email\MailBuilderService;
use HiEvents\Services\Domain\Order\OfflinePaymentInstructionsRenderService;
use Mockery as m;
use Tests\TestCase;

class MailBuilderServiceTest extends TestCase
{
    public function test_order_summary_blade_fallback_receives_rendered_instructions(): void
    {
        $emailTemplateService = m::mock(EmailTemplateService::class);
        $emailTemplateService->shouldReceive('getTemplateByType')->andReturn(null);

        $service = new MailBuilderService(
            $emailTemplateService,
            app(EmailTokenContextBuilder::class),
            app(OfflinePaymentInstructionsRenderService::class),
        );

        $organizer = (new OrganizerDomainObject())
            ->setId(1)
            ->setName('Example Organizer')
            ->setEmail('organizer@example.com');

        $settings = (new EventSettingDomainObject())
            ->setId(20)
            ->setEventId(10)
            ->setSupportEmail('support@example.com')
            ->setOfflinePaymentInstructions('<p>Use {{ order.number }} as your reference</p>');

        $event = (new EventDomainObject())
            ->setId(10)
            ->setAccountId(1)
            ->setTitle('Summer Session')
            ->setStartDate('2026-08-15 18:00:00')
            ->setCurrency('GBP')
            ->setTimezone('UTC')
            ->setOrganizer($organizer)
            ->setEventSettings($settings);

        $order = (new OrderDomainObject())
            ->setId(30)
            ->setEventId(10)
            ->setShortId('order-short-id')
            ->setPublicId('ORD-12345')
            ->setFirstName('Jane')
            ->setLastName('Buyer')
            ->setEmail('buyer@example.com')
            ->setTotalGross(125.50)
            ->setCurrency('GBP')
            ->setCreatedAt('2026-08-01 12:00:00')
            ->setStatus(OrderStatus::AWAITING_OFFLINE_PAYMENT->name)
            ->setPaymentStatus(OrderPaymentStatus::AWAITING_OFFLINE_PAYMENT->name)
            ->setPaymentProvider(PaymentProviders::OFFLINE->value);

        $service->buildOrderSummaryMail($order, $event, $settings, $organizer);

        $this->assertSame(
            '<p>Use ORD-12345 as your reference</p>',
            $settings->getOfflinePaymentInstructions(),
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
