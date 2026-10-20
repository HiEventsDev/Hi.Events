<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Mail\SendEventEmailMessagesService;
use Illuminate\Contracts\Bus\Dispatcher;
use Mockery;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Log\Logger;
use Tests\TestCase;

class SendEventEmailMessagesServiceTest extends TestCase
{
    public function test_occurrence_filter_is_empty_for_a_message_queued_by_a_previous_release(): void
    {
        $messageData = (new ReflectionClass(SendMessageDTO::class))->newInstanceWithoutConstructor();

        $service = new SendEventEmailMessagesService(
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(AttendeeRepositoryInterface::class),
            Mockery::mock(EventRepositoryInterface::class),
            Mockery::mock(MessageRepositoryInterface::class),
            Mockery::mock(UserRepositoryInterface::class),
            Mockery::mock(Logger::class),
            Mockery::mock(Dispatcher::class),
        );

        $occurrenceWhere = new ReflectionMethod($service, 'occurrenceWhere');

        $this->assertSame([], $occurrenceWhere->invoke($service, $messageData));
    }
}
