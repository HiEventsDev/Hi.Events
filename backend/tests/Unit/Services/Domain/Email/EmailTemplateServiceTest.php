<?php

namespace Tests\Unit\Services\Domain\Email;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Services\Domain\Email\EmailContextHtmlEscaper;
use HiEvents\Services\Domain\Email\EmailTemplateService;
use HiEvents\Services\Domain\Email\EmailTokenContextBuilder;
use HiEvents\Services\Infrastructure\Email\LiquidTemplateRenderer;
use Mockery;
use Tests\TestCase;

class EmailTemplateServiceTest extends TestCase
{
    private EmailTemplateService $emailTemplateService;

    private EmailTemplateRepositoryInterface $mockRepository;

    private LiquidTemplateRenderer $mockLiquidRenderer;

    private EmailTokenContextBuilder $mockTokenBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(EmailTemplateRepositoryInterface::class);
        $this->mockLiquidRenderer = Mockery::mock(LiquidTemplateRenderer::class);
        $this->mockTokenBuilder = Mockery::mock(EmailTokenContextBuilder::class);

        $this->emailTemplateService = new EmailTemplateService(
            $this->mockRepository,
            $this->mockLiquidRenderer,
            $this->mockTokenBuilder,
            new EmailContextHtmlEscaper,
        );
    }

    public function test_render_template_escapes_user_content_in_body_but_not_subject(): void
    {
        $service = new EmailTemplateService(
            $this->mockRepository,
            new LiquidTemplateRenderer,
            $this->mockTokenBuilder,
            new EmailContextHtmlEscaper,
        );

        $template = Mockery::mock(EmailTemplateDomainObject::class, [
            'getSubject' => 'Order for {{ event.title }}',
            'getBody' => 'Hi {{ order.first_name }}, welcome to {{ event.title }}',
            'getCta' => null,
        ]);

        $result = $service->renderTemplate($template, [
            'event' => ['title' => 'Rock & Roll <b>Night</b>'],
            'order' => ['first_name' => '<img src=x onerror=alert(1)>'],
        ]);

        $this->assertSame(
            'Hi &lt;img src=x onerror=alert(1)&gt;, welcome to Rock &amp; Roll &lt;b&gt;Night&lt;/b&gt;',
            $result->body
        );
        $this->assertSame('Order for Rock & Roll <b>Night</b>', $result->subject);
    }

    public function test_render_template_keeps_purified_html_tokens_unescaped_in_body(): void
    {
        $service = new EmailTemplateService(
            $this->mockRepository,
            new LiquidTemplateRenderer,
            $this->mockTokenBuilder,
            new EmailContextHtmlEscaper,
        );

        $template = Mockery::mock(EmailTemplateDomainObject::class, [
            'getSubject' => 'Your order',
            'getBody' => '{{ settings.post_checkout_message }} {{ event.description }}',
            'getCta' => null,
        ]);

        $result = $service->renderTemplate($template, [
            'settings' => ['post_checkout_message' => '<strong>Thanks!</strong>'],
            'event' => ['description' => '<p>Details</p>'],
        ]);

        $this->assertSame('<strong>Thanks!</strong> <p>Details</p>', $result->body);
    }

    public function test_gets_event_level_template_when_exists(): void
    {
        $eventTemplate = $this->createMockTemplate(
            'event-template-id',
            EmailTemplateType::ORDER_CONFIRMATION,
            1,
            1,
            1,
            true
        );

        $this->mockRepository
            ->shouldReceive('findByTypeWithFallback')
            ->with(
                EmailTemplateType::ORDER_CONFIRMATION,
                1,
                1,
                1
            )
            ->once()
            ->andReturn($eventTemplate);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ORDER_CONFIRMATION,
            1,
            1,
            1
        );

        $this->assertSame($eventTemplate, $result);
    }

    public function test_falls_back_to_organizer_template_when_no_event_template(): void
    {
        $organizerTemplate = $this->createMockTemplate(
            'organizer-template-id',
            EmailTemplateType::ORDER_CONFIRMATION,
            1,
            1,
            null,
            true
        );

        $this->mockRepository
            ->shouldReceive('findByTypeWithFallback')
            ->with(
                EmailTemplateType::ORDER_CONFIRMATION,
                1,
                1,
                1
            )
            ->once()
            ->andReturn($organizerTemplate);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ORDER_CONFIRMATION,
            1,
            1,
            1
        );

        $this->assertSame($organizerTemplate, $result);
    }

    public function test_returns_null_when_no_templates_exist(): void
    {
        $this->mockRepository
            ->shouldReceive('findByTypeWithFallback')
            ->with(
                EmailTemplateType::ORDER_CONFIRMATION,
                1,
                1,
                1
            )
            ->once()
            ->andReturn(null);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ORDER_CONFIRMATION,
            1,
            1,
            1
        );

        $this->assertNull($result);
    }

    public function test_gets_organizer_level_template_when_no_event_id(): void
    {
        $organizerTemplate = $this->createMockTemplate(
            'organizer-template-id',
            EmailTemplateType::ATTENDEE_TICKET,
            1,
            1,
            null,
            true
        );

        $this->mockRepository
            ->shouldReceive('findByTypeWithFallback')
            ->with(
                EmailTemplateType::ATTENDEE_TICKET,
                1,
                null,
                1
            )
            ->once()
            ->andReturn($organizerTemplate);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ATTENDEE_TICKET,
            1,
            null,
            1
        );

        $this->assertSame($organizerTemplate, $result);
    }

    public function test_prefers_active_templates_over_inactive(): void
    {
        $activeTemplate = $this->createMockTemplate(
            'active-template-id',
            EmailTemplateType::ORDER_CONFIRMATION,
            1,
            1,
            1,
            true
        );

        $this->mockRepository
            ->shouldReceive('findByTypeWithFallback')
            ->with(
                EmailTemplateType::ORDER_CONFIRMATION,
                1,
                1,
                1
            )
            ->once()
            ->andReturn($activeTemplate);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ORDER_CONFIRMATION,
            1,
            1,
            1
        );

        $this->assertSame($activeTemplate, $result);
        $this->assertTrue($result->getIsActive());
    }

    public function test_handles_different_template_types(): void
    {
        $attendeeTicketTemplate = $this->createMockTemplate(
            'ticket-template-id',
            EmailTemplateType::ATTENDEE_TICKET,
            1,
            1,
            null,
            true
        );

        $this->mockRepository
            ->shouldReceive('findByTypeWithFallback')
            ->with(
                EmailTemplateType::ATTENDEE_TICKET,
                1,
                null,
                1
            )
            ->once()
            ->andReturn($attendeeTicketTemplate);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ATTENDEE_TICKET,
            1,
            null,
            1
        );

        $this->assertSame($attendeeTicketTemplate, $result);
        $this->assertEquals(EmailTemplateType::ATTENDEE_TICKET->value, $result->getTemplateType());
    }

    private function createMockTemplate(
        string $id,
        EmailTemplateType $type,
        int $accountId,
        int $organizerId,
        ?int $eventId,
        bool $isActive
    ): EmailTemplateDomainObject {
        return Mockery::mock(EmailTemplateDomainObject::class, [
            'getId' => $id,
            'getTemplateType' => $type->value,
            'getAccountId' => $accountId,
            'getOrganizerId' => $organizerId,
            'getEventId' => $eventId,
            'getSubject' => 'Test Subject',
            'getBody' => 'Test Body {{ customer.name }}',
            'getIsActive' => $isActive,
            'getEngine' => 'liquid',
        ]);
    }
}
