<?php

namespace Tests\Unit\Services\Application\Handlers\Message;

use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\GetMessageRecipientsHandler;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery as m;
use Tests\TestCase;

class GetMessageRecipientsHandlerTest extends TestCase
{
    private OutgoingMessageRepositoryInterface $outgoingMessageRepository;
    private MessageRepositoryInterface $messageRepository;
    private GetMessageRecipientsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outgoingMessageRepository = m::mock(OutgoingMessageRepositoryInterface::class);
        $this->messageRepository = m::mock(MessageRepositoryInterface::class);
        $this->handler = new GetMessageRecipientsHandler(
            $this->outgoingMessageRepository,
            $this->messageRepository,
        );
    }

    public function testHandleReturnsPaginatedRecipients(): void
    {
        $eventId = 10;
        $messageId = 20;
        $params = QueryParamsDTO::fromArray(['per_page' => 100, 'page' => 1]);

        $message = m::mock(MessageDomainObject::class);
        $this->messageRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => $messageId, 'event_id' => $eventId])
            ->andReturn($message);

        $paginator = new LengthAwarePaginator([], 0, 100);
        $this->outgoingMessageRepository
            ->shouldReceive('paginateWhere')
            ->once()
            ->with(['event_id' => $eventId, 'message_id' => $messageId], 100)
            ->andReturn($paginator);

        $result = $this->handler->handle($eventId, $messageId, $params);

        $this->assertSame($paginator, $result);
    }

    public function testHandleUsesDefaultPerPageFromDto(): void
    {
        $eventId = 5;
        $messageId = 15;
        $params = QueryParamsDTO::fromArray(['page' => 1]);

        $message = m::mock(MessageDomainObject::class);
        $this->messageRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => $messageId, 'event_id' => $eventId])
            ->andReturn($message);

        $paginator = new LengthAwarePaginator([], 0, 25);
        $this->outgoingMessageRepository
            ->shouldReceive('paginateWhere')
            ->once()
            ->with(['event_id' => $eventId, 'message_id' => $messageId], 25)
            ->andReturn($paginator);

        $result = $this->handler->handle($eventId, $messageId, $params);

        $this->assertSame($paginator, $result);
    }

    public function testHandleThrowsNotFoundWhenMessageDoesNotExist(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->messageRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 999, 'event_id' => 1])
            ->andReturn(null);

        $this->outgoingMessageRepository->shouldNotReceive('paginateWhere');

        $params = QueryParamsDTO::fromArray(['page' => 1]);
        $this->handler->handle(1, 999, $params);
    }

    public function testHandleThrowsNotFoundWhenMessageBelongsToDifferentEvent(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->messageRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 20, 'event_id' => 99])
            ->andReturn(null);

        $this->outgoingMessageRepository->shouldNotReceive('paginateWhere');

        $params = QueryParamsDTO::fromArray(['page' => 1]);
        $this->handler->handle(99, 20, $params);
    }
}
