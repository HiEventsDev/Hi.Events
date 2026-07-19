<?php

namespace Tests\Unit\Services\Application\Handlers\EmailTemplate;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\UpsertEmailTemplateDTO;
use HiEvents\Services\Application\Handlers\EmailTemplate\UpdateEmailTemplateHandler;
use HiEvents\Services\Domain\Email\EmailTemplateService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class UpdateEmailTemplateHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private EmailTemplateRepositoryInterface $repository;

    private EmailTemplateService $emailTemplateService;

    private HtmlPurifierService $purifier;

    private UpdateEmailTemplateHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = m::mock(EmailTemplateRepositoryInterface::class);
        $this->emailTemplateService = m::mock(EmailTemplateService::class);
        $this->purifier = m::mock(HtmlPurifierService::class);

        $this->handler = new UpdateEmailTemplateHandler(
            $this->repository,
            $this->emailTemplateService,
            $this->purifier,
        );
    }

    public function test_attendee_ticket_template_keeps_ticket_url_token_when_caller_passes_no_token(): void
    {
        $existing = (new EmailTemplateDomainObject)
            ->setId(42)
            ->setAccountId(1)
            ->setTemplateType(EmailTemplateType::ATTENDEE_TICKET->value);

        $this->emailTemplateService
            ->shouldReceive('validateTemplate')
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->repository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing);

        $this->emailTemplateService
            ->shouldReceive('getDefaultTemplate')
            ->with(EmailTemplateType::ATTENDEE_TICKET)
            ->andReturn([
                'subject' => 's',
                'body' => 'b',
                'cta' => ['label' => 'View Ticket', 'url_token' => 'ticket.url'],
            ]);

        $this->purifier->shouldReceive('purify')->andReturnUsing(fn ($v) => $v);

        $this->repository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(42, m::on(function (array $payload): bool {
                return $payload['cta'] === ['label' => 'My Custom Label', 'url_token' => 'ticket.url'];
            }))
            ->andReturn($existing);

        $this->handler->handle(new UpsertEmailTemplateDTO(
            account_id: 1,
            template_type: EmailTemplateType::ORDER_CONFIRMATION,
            subject: 'subj',
            body: 'body',
            id: 42,
            cta: ['label' => 'My Custom Label'],
        ));
    }

    public function test_attendee_ticket_template_overrides_order_url_token_passed_in(): void
    {
        $existing = (new EmailTemplateDomainObject)
            ->setId(42)
            ->setAccountId(1)
            ->setTemplateType(EmailTemplateType::ATTENDEE_TICKET->value);

        $this->emailTemplateService
            ->shouldReceive('validateTemplate')
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->repository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing);

        $this->emailTemplateService
            ->shouldReceive('getDefaultTemplate')
            ->with(EmailTemplateType::ATTENDEE_TICKET)
            ->andReturn([
                'subject' => 's',
                'body' => 'b',
                'cta' => ['label' => 'View Ticket', 'url_token' => 'ticket.url'],
            ]);

        $this->purifier->shouldReceive('purify')->andReturnUsing(fn ($v) => $v);

        $this->repository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(42, m::on(function (array $payload): bool {
                return $payload['cta']['url_token'] === 'ticket.url';
            }))
            ->andReturn($existing);

        $this->handler->handle(new UpsertEmailTemplateDTO(
            account_id: 1,
            template_type: EmailTemplateType::ORDER_CONFIRMATION,
            subject: 'subj',
            body: 'body',
            id: 42,
            cta: ['label' => 'View Ticket', 'url_token' => 'order.url'],
        ));
    }

    public function test_order_confirmation_template_uses_order_url_token(): void
    {
        $existing = (new EmailTemplateDomainObject)
            ->setId(43)
            ->setAccountId(1)
            ->setTemplateType(EmailTemplateType::ORDER_CONFIRMATION->value);

        $this->emailTemplateService
            ->shouldReceive('validateTemplate')
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->repository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing);

        $this->emailTemplateService
            ->shouldReceive('getDefaultTemplate')
            ->with(EmailTemplateType::ORDER_CONFIRMATION)
            ->andReturn([
                'subject' => 's',
                'body' => 'b',
                'cta' => ['label' => 'View Order & Tickets', 'url_token' => 'order.url'],
            ]);

        $this->purifier->shouldReceive('purify')->andReturnUsing(fn ($v) => $v);

        $this->repository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(43, m::on(function (array $payload): bool {
                return $payload['cta']['url_token'] === 'order.url'
                    && $payload['cta']['label'] === 'View Order';
            }))
            ->andReturn($existing);

        $this->handler->handle(new UpsertEmailTemplateDTO(
            account_id: 1,
            template_type: EmailTemplateType::ORDER_CONFIRMATION,
            subject: 'subj',
            body: 'body',
            id: 43,
            cta: ['label' => 'View Order'],
        ));
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
