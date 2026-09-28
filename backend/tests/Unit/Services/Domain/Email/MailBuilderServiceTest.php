<?php

namespace Tests\Unit\Services\Domain\Email;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Cashless\CashlessTopupConfirmationMail;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;
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

        $organizer = (new OrganizerDomainObject)
            ->setId(1)
            ->setName('Example Organizer')
            ->setEmail('organizer@example.com');

        $settings = (new EventSettingDomainObject)
            ->setId(20)
            ->setEventId(10)
            ->setSupportEmail('support@example.com')
            ->setOfflinePaymentInstructions('<p>Use {{ order.number }} as your reference</p>');

        $event = (new EventDomainObject)
            ->setId(10)
            ->setAccountId(1)
            ->setTitle('Summer Session')
            ->setCurrency('GBP')
            ->setTimezone('UTC')
            ->setOrganizer($organizer)
            ->setEventSettings($settings);

        $order = (new OrderDomainObject)
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

    public function test_cashless_topup_mail_uses_the_default_view_when_no_template_exists(): void
    {
        $emailTemplateService = m::mock(EmailTemplateService::class);
        $emailTemplateService->shouldReceive('getTemplateByType')->andReturn(null);
        $emailTemplateService->shouldNotReceive('renderTemplate');

        $mail = $this->makeCashlessMail($emailTemplateService);

        $this->assertSame('emails.cashless.topup-confirmation', $mail->content()->markdown);
    }

    public function test_cashless_topup_mail_renders_the_custom_template_when_one_exists(): void
    {
        $template = new EmailTemplateDomainObject;
        $rendered = new RenderedEmailTemplateDTO('Custom subject', '<p>Body</p>', null);

        $emailTemplateService = m::mock(EmailTemplateService::class);
        $emailTemplateService->shouldReceive('getTemplateByType')
            ->withArgs(fn ($type) => $type === EmailTemplateType::CASHLESS_TOPUP)
            ->andReturn($template);
        $emailTemplateService->shouldReceive('renderTemplate')
            ->withArgs(fn ($given, array $context) => $given === $template
                && $context['cashless']['topped_up_amount'] === '$20.00'
                && $context['cashless']['new_balance'] === '$35.00')
            ->andReturn($rendered);

        $mail = $this->makeCashlessMail($emailTemplateService);

        $this->assertSame('emails.custom-template', $mail->content()->markdown);
        $this->assertSame('Custom subject', $mail->envelope()->subject);
    }

    private function makeCashlessMail(EmailTemplateService $emailTemplateService): CashlessTopupConfirmationMail
    {
        $service = new MailBuilderService(
            $emailTemplateService,
            app(EmailTokenContextBuilder::class),
            app(OfflinePaymentInstructionsRenderService::class),
        );

        $organizer = (new OrganizerDomainObject)->setId(1)->setName('Organizer')->setEmail('organizer@example.com');
        $settings = (new EventSettingDomainObject)->setSupportEmail('support@example.com');
        $event = (new EventDomainObject)
            ->setId(10)
            ->setAccountId(1)
            ->setTitle('Summer Fest')
            ->setCurrency('USD')
            ->setTimezone('UTC');
        $attendee = (new AttendeeDomainObject)
            ->setShortId('a_abc')
            ->setFirstName('Marie')
            ->setLastName('Durand')
            ->setEmail('marie@example.com');
        $wallet = (new CashlessWalletDomainObject)->setCurrency('USD');
        $transaction = (new CashlessTransactionDomainObject)->setAmount(20.0)->setBalanceAfter(35.0);

        return $service->buildCashlessTopupConfirmationMail($wallet, $transaction, $attendee, $event, $settings, $organizer);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
