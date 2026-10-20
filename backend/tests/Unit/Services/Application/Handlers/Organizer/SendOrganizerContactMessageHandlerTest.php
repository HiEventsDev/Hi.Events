<?php

namespace Tests\Unit\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use HiEvents\Mail\Organizer\OrganizerContactEmail;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\SendOrganizerContactMessageDTO;
use HiEvents\Services\Application\Handlers\Organizer\SendOrganizerContactMessageHandler;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

class SendOrganizerContactMessageHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OrganizerRepositoryInterface|MockInterface $organizerRepository;

    private Mailer|MockInterface $mailer;

    private SendOrganizerContactMessageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = Mockery::mock(Mailer::class);
        $this->organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);

        $purifier = Mockery::mock(HtmlPurifierService::class);
        $purifier->shouldReceive('purify')->andReturnUsing(fn (?string $value) => $value);

        $this->handler = new SendOrganizerContactMessageHandler(
            $this->mailer,
            $this->organizerRepository,
            $purifier,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sends_messages_to_live_organizers(): void
    {
        $this->givenOrganizer(OrganizerStatus::LIVE);

        $pendingMail = Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')->once()->with(Mockery::type(OrganizerContactEmail::class));
        $this->mailer->shouldReceive('to')->once()->with('organizer@example.com', 'Organizer')->andReturn($pendingMail);

        $this->handler->handle($this->dto());
    }

    public function test_refuses_to_contact_organizers_that_are_not_live(): void
    {
        $this->givenOrganizer(OrganizerStatus::DRAFT);

        $this->mailer->shouldNotReceive('to');
        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($this->dto());
    }

    private function givenOrganizer(OrganizerStatus $status): void
    {
        $this->organizerRepository->shouldReceive('findById')->with(3)->andReturn(
            (new OrganizerDomainObject)
                ->setId(3)
                ->setAccountId(9)
                ->setName('Organizer')
                ->setEmail('organizer@example.com')
                ->setStatus($status->value)
        );
    }

    private function dto(): SendOrganizerContactMessageDTO
    {
        return new SendOrganizerContactMessageDTO(
            organizer_id: 3,
            name: 'Sender',
            email: 'sender@example.com',
            message: 'Hello',
        );
    }
}
