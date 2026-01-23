<?php

namespace Tests\Unit\Services\Domain\Email;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Services\Domain\Email\EmailTemplateService;
use HiEvents\Services\Domain\Email\EmailTokenContextBuilder;
use HiEvents\Services\Infrastructure\Email\LiquidTemplateRenderer;
use Tests\TestCase;
use Mockery;

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
            $this->mockTokenBuilder
        );
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
                1, // accountId
                1, // eventId
                1  // organizerId
            )
            ->once()
            ->andReturn($eventTemplate);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ORDER_CONFIRMATION,
            1, // accountId
            1, // eventId
            1  // organizerId
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
                1, // accountId
                1, // eventId
                1  // organizerId
            )
            ->once()
            ->andReturn($organizerTemplate);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ORDER_CONFIRMATION,
            1, // accountId
            1, // eventId
            1  // organizerId
        );

        $this->assertSame($organizerTemplate, $result);
    }

    public function test_returns_null_when_no_templates_exist(): void
    {
        $this->mockRepository
            ->shouldReceive('findByTypeWithFallback')
            ->with(
                EmailTemplateType::ORDER_CONFIRMATION,
                1, // accountId
                1, // eventId
                1  // organizerId
            )
            ->once()
            ->andReturn(null);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ORDER_CONFIRMATION,
            1, // accountId
            1, // eventId
            1  // organizerId
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
                1, // accountId
                null, // eventId
                1  // organizerId
            )
            ->once()
            ->andReturn($organizerTemplate);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ATTENDEE_TICKET,
            1, // accountId
            null, // eventId
            1  // organizerId
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
                1, // accountId
                1, // eventId
                1  // organizerId
            )
            ->once()
            ->andReturn($activeTemplate);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ORDER_CONFIRMATION,
            1, // accountId
            1, // eventId
            1  // organizerId
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
                1, // accountId
                null, // eventId
                1  // organizerId
            )
            ->once()
            ->andReturn($attendeeTicketTemplate);

        $result = $this->emailTemplateService->getTemplateByType(
            EmailTemplateType::ATTENDEE_TICKET,
            1, // accountId
            null, // eventId
            1  // organizerId
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
