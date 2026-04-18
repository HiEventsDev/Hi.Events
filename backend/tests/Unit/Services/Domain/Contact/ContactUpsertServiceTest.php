<?php

namespace Tests\Unit\Services\Domain\Contact;

use HiEvents\DomainObjects\ContactDomainObject;
use HiEvents\Repository\Interfaces\ContactRepositoryInterface;
use HiEvents\Services\Domain\Contact\ContactUpsertService;
use Mockery as m;
use Tests\TestCase;

class ContactUpsertServiceTest extends TestCase
{
    private ContactRepositoryInterface $contactRepository;
    private ContactUpsertService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contactRepository = m::mock(ContactRepositoryInterface::class);
        $this->service = new ContactUpsertService($this->contactRepository);
    }

    public function testFindOrCreateContactCreatesNewWhenNotFound(): void
    {
        $expectedContact = (new ContactDomainObject())
            ->setId(1)
            ->setAccountId(42)
            ->setEmail('test@example.com')
            ->setFirstName('John')
            ->setLastName('Doe');

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

        $result = $this->service->findOrCreateContact(42, 'test@example.com', 'John', 'Doe');

        $this->assertSame($expectedContact, $result);
    }

    public function testFindOrCreateContactReturnsExistingContact(): void
    {
        $existingContact = (new ContactDomainObject())
            ->setId(1)
            ->setAccountId(42)
            ->setEmail('test@example.com')
            ->setFirstName('John')
            ->setLastName('Doe');

        $this->contactRepository
            ->shouldReceive('findByEmailAndAccountId')
            ->once()
            ->with('test@example.com', 42)
            ->andReturn($existingContact);

        $result = $this->service->findOrCreateContact(42, 'test@example.com', 'John', 'Doe');

        $this->assertSame($existingContact, $result);
    }

    public function testFindOrCreateContactUpdatesNullNameOnExisting(): void
    {
        $existingContact = (new ContactDomainObject())
            ->setId(1)
            ->setAccountId(42)
            ->setEmail('test@example.com')
            ->setFirstName(null)
            ->setLastName(null);

        $updatedContact = (new ContactDomainObject())
            ->setId(1)
            ->setAccountId(42)
            ->setEmail('test@example.com')
            ->setFirstName('Jane')
            ->setLastName('Smith');

        $this->contactRepository
            ->shouldReceive('findByEmailAndAccountId')
            ->once()
            ->with('test@example.com', 42)
            ->andReturn($existingContact);

        $this->contactRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, [
                ContactDomainObject::FIRST_NAME => 'Jane',
                ContactDomainObject::LAST_NAME => 'Smith',
            ]);

        $this->contactRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($updatedContact);

        $result = $this->service->findOrCreateContact(42, 'test@example.com', 'Jane', 'Smith');

        $this->assertEquals('Jane', $result->getFirstName());
        $this->assertEquals('Smith', $result->getLastName());
    }

    public function testUpdateContactAttributesMergesAndTracksHistory(): void
    {
        $contact = (new ContactDomainObject())
            ->setId(1)
            ->setAttributes(['role' => 'attendee'])
            ->setAttributesHistory([]);

        $updatedContact = (new ContactDomainObject())
            ->setId(1)
            ->setAttributes(['role' => 'volunteer', 'county' => 'Fairfax']);

        $this->contactRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, m::on(function ($data) {
                return $data[ContactDomainObject::ATTRIBUTES] === ['role' => 'volunteer', 'county' => 'Fairfax']
                    && count($data[ContactDomainObject::ATTRIBUTES_HISTORY]) === 1
                    && $data[ContactDomainObject::ATTRIBUTES_HISTORY][0]['old_values'] === ['role' => 'attendee']
                    && $data[ContactDomainObject::ATTRIBUTES_HISTORY][0]['new_values'] === ['role' => 'volunteer', 'county' => 'Fairfax']
                    && $data[ContactDomainObject::ATTRIBUTES_HISTORY][0]['changed_by'] === 99;
            }));

        $this->contactRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($updatedContact);

        $result = $this->service->updateContactAttributes(
            $contact,
            ['role' => 'volunteer', 'county' => 'Fairfax'],
            99,
        );

        $this->assertSame($updatedContact, $result);
    }

    public function testUpdateContactAttributesNoOpWhenNoChanges(): void
    {
        $contact = (new ContactDomainObject())
            ->setId(1)
            ->setAttributes(['role' => 'attendee'])
            ->setAttributesHistory([]);

        $this->contactRepository->shouldNotReceive('updateFromArray');

        $result = $this->service->updateContactAttributes(
            $contact,
            ['role' => 'attendee'],
            99,
        );

        $this->assertSame($contact, $result);
    }

    public function testUpdateContactAttributesAppendsProcessedIdsWithoutValueChanges(): void
    {
        $contact = (new ContactDomainObject())
            ->setId(1)
            ->setAttributes(['county' => 'walker'])
            ->setAttributesHistory([])
            ->setProcessedQuestionAnswerIds([100]);

        $refreshed = (new ContactDomainObject())->setId(1);

        $this->contactRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, m::on(function ($data) {
                return !array_key_exists(ContactDomainObject::ATTRIBUTES, $data)
                    && !array_key_exists(ContactDomainObject::ATTRIBUTES_HISTORY, $data)
                    && $data[ContactDomainObject::PROCESSED_QUESTION_ANSWER_IDS] === [100, 200, 201];
            }));

        $this->contactRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($refreshed);

        $result = $this->service->updateContactAttributes(
            $contact,
            ['county' => 'walker'],
            99,
            [200, 201],
        );

        $this->assertSame($refreshed, $result);
    }

    public function testUpdateContactAttributesDedupesProcessedIds(): void
    {
        $contact = (new ContactDomainObject())
            ->setId(1)
            ->setAttributes(['county' => 'walker'])
            ->setAttributesHistory([])
            ->setProcessedQuestionAnswerIds([100, 200]);

        $this->contactRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, m::on(function ($data) {
                return $data[ContactDomainObject::PROCESSED_QUESTION_ANSWER_IDS] === [100, 200, 300];
            }));

        $refreshed = (new ContactDomainObject())->setId(1);
        $this->contactRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($refreshed);

        $result = $this->service->updateContactAttributes(
            $contact,
            ['county' => 'walker'],
            99,
            [200, 100, 300, 200],
        );

        $this->assertSame($refreshed, $result);
    }

    public function testUpdateContactAttributesPersistsValueChangesAndProcessedIdsTogether(): void
    {
        $contact = (new ContactDomainObject())
            ->setId(1)
            ->setAttributes(['county' => 'walkkker'])
            ->setAttributesHistory([])
            ->setProcessedQuestionAnswerIds([]);

        $this->contactRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, m::on(function ($data) {
                $hasAttrUpdate = $data[ContactDomainObject::ATTRIBUTES] === ['county' => 'walker'];
                $hasHistoryEntry = count($data[ContactDomainObject::ATTRIBUTES_HISTORY]) === 1
                    && $data[ContactDomainObject::ATTRIBUTES_HISTORY][0]['old_values'] === ['county' => 'walkkker']
                    && $data[ContactDomainObject::ATTRIBUTES_HISTORY][0]['new_values'] === ['county' => 'walker'];
                $hasProcessedIds = $data[ContactDomainObject::PROCESSED_QUESTION_ANSWER_IDS] === [400, 401];

                return $hasAttrUpdate && $hasHistoryEntry && $hasProcessedIds;
            }));

        $refreshed = (new ContactDomainObject())->setId(1);
        $this->contactRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($refreshed);

        $result = $this->service->updateContactAttributes(
            $contact,
            ['county' => 'walker'],
            99,
            [400, 401],
        );

        $this->assertSame($refreshed, $result);
    }

    public function testUpdateContactAttributesNoOpWhenNoValueChangesAndNoProcessedIds(): void
    {
        $contact = (new ContactDomainObject())
            ->setId(1)
            ->setAttributes(['county' => 'walker'])
            ->setAttributesHistory([])
            ->setProcessedQuestionAnswerIds([100]);

        $this->contactRepository->shouldNotReceive('updateFromArray');

        $result = $this->service->updateContactAttributes(
            $contact,
            ['county' => 'walker'],
            99,
            [],
        );

        $this->assertSame($contact, $result);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
