<?php

namespace Tests\Unit\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\ContactDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\ContactRepositoryInterface;
use HiEvents\Services\Application\Handlers\Contact\CreateContactHandler;
use HiEvents\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use Mockery as m;
use Tests\TestCase;

class CreateContactHandlerTest extends TestCase
{
    private ContactRepositoryInterface $contactRepository;
    private CreateContactHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contactRepository = m::mock(ContactRepositoryInterface::class);
        $this->handler = new CreateContactHandler($this->contactRepository);
    }

    public function testHandleSuccessfullyCreatesContact(): void
    {
        $dto = UpsertContactDTO::from([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'account_id' => 42,
        ]);

        $expectedContact = m::mock(ContactDomainObject::class);

        $this->contactRepository
            ->shouldReceive('findByEmailAndAccountId')
            ->once()
            ->with('test@example.com', 42)
            ->andReturn(null);

        $this->contactRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data[ContactDomainObject::ACCOUNT_ID] === 42
                    && $data[ContactDomainObject::EMAIL] === 'test@example.com'
                    && $data[ContactDomainObject::FIRST_NAME] === 'John'
                    && $data[ContactDomainObject::LAST_NAME] === 'Doe';
            }))
            ->andReturn($expectedContact);

        $result = $this->handler->handle($dto);

        $this->assertSame($expectedContact, $result);
    }

    public function testHandleThrowsExceptionWhenEmailAlreadyExists(): void
    {
        $dto = UpsertContactDTO::from([
            'email' => 'existing@example.com',
            'account_id' => 42,
        ]);

        $existingContact = m::mock(ContactDomainObject::class);

        $this->contactRepository
            ->shouldReceive('findByEmailAndAccountId')
            ->once()
            ->with('existing@example.com', 42)
            ->andReturn($existingContact);

        $this->contactRepository
            ->shouldNotReceive('create');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle($dto);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
