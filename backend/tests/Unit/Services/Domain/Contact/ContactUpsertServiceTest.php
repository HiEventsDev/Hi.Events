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

    public function test_find_or_create_contact_creates_new_when_not_found(): void
    {
        $expectedContact = (new ContactDomainObject)
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

    public function test_find_or_create_contact_returns_existing_contact(): void
    {
        $existingContact = (new ContactDomainObject)
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

    public function test_find_or_create_contact_updates_null_name_on_existing(): void
    {
        $existingContact = (new ContactDomainObject)
            ->setId(1)
            ->setAccountId(42)
            ->setEmail('test@example.com')
            ->setFirstName(null)
            ->setLastName(null);

        $updatedContact = (new ContactDomainObject)
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

    public function test_update_contact_attributes_merges_and_tracks_history(): void
    {
        $contact = (new ContactDomainObject)
            ->setId(1)
            ->setAttributes(['role' => 'attendee'])
            ->setAttributesHistory([]);

        $updatedContact = (new ContactDomainObject)
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

    public function test_update_contact_attributes_no_op_when_no_changes(): void
    {
        $contact = (new ContactDomainObject)
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

    public function test_update_contact_attributes_appends_processed_ids_without_value_changes(): void
    {
        $contact = (new ContactDomainObject)
            ->setId(1)
            ->setAttributes(['county' => 'walker'])
            ->setAttributesHistory([])
            ->setProcessedQuestionAnswerIds([100]);

        $refreshed = (new ContactDomainObject)->setId(1);

        $this->contactRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, m::on(function ($data) {
                return ! array_key_exists(ContactDomainObject::ATTRIBUTES, $data)
                    && ! array_key_exists(ContactDomainObject::ATTRIBUTES_HISTORY, $data)
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

    public function test_update_contact_attributes_dedupes_processed_ids(): void
    {
        $contact = (new ContactDomainObject)
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

        $refreshed = (new ContactDomainObject)->setId(1);
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

    public function test_update_contact_attributes_persists_value_changes_and_processed_ids_together(): void
    {
        $contact = (new ContactDomainObject)
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

        $refreshed = (new ContactDomainObject)->setId(1);
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

    public function test_update_contact_attributes_no_op_when_no_value_changes_and_no_processed_ids(): void
    {
        $contact = (new ContactDomainObject)
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

    public function test_update_contact_attributes_appends_ignored_ids(): void
    {
        $contact = (new ContactDomainObject)
            ->setId(1)
            ->setAttributes(['county' => 'walker'])
            ->setAttributesHistory([])
            ->setProcessedQuestionAnswerIds([100])
            ->setIgnoredQuestionAnswerIds([100]);

        $refreshed = (new ContactDomainObject)->setId(1);
        $this->contactRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, m::on(function ($data) {
                return ! array_key_exists(ContactDomainObject::ATTRIBUTES, $data)
                    && ! array_key_exists(ContactDomainObject::ATTRIBUTES_HISTORY, $data)
                    && $data[ContactDomainObject::PROCESSED_QUESTION_ANSWER_IDS] === [100, 200, 201]
                    && $data[ContactDomainObject::IGNORED_QUESTION_ANSWER_IDS] === [100, 200, 201];
            }));
        $this->contactRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($refreshed);

        $result = $this->service->updateContactAttributes(
            contact: $contact,
            newAttributes: [],
            changedByUserId: 99,
            sourceQuestionAnswerIds: [200, 201],
            addedIgnoredQuestionAnswerIds: [200, 201],
        );

        $this->assertSame($refreshed, $result);
    }

    public function test_update_contact_attributes_removes_ignored_ids_when_updating(): void
    {
        $contact = (new ContactDomainObject)
            ->setId(1)
            ->setAttributes(['county' => 'walkkker'])
            ->setAttributesHistory([])
            ->setProcessedQuestionAnswerIds([200])
            ->setIgnoredQuestionAnswerIds([200, 201, 202]);

        $refreshed = (new ContactDomainObject)->setId(1);
        $this->contactRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, m::on(function ($data) {
                return $data[ContactDomainObject::ATTRIBUTES] === ['county' => 'walker']
                    && $data[ContactDomainObject::PROCESSED_QUESTION_ANSWER_IDS] === [200, 201]
                    && $data[ContactDomainObject::IGNORED_QUESTION_ANSWER_IDS] === [202];
            }));
        $this->contactRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($refreshed);

        $result = $this->service->updateContactAttributes(
            contact: $contact,
            newAttributes: ['county' => 'walker'],
            changedByUserId: 99,
            sourceQuestionAnswerIds: [201],
            addedIgnoredQuestionAnswerIds: [],
            removedIgnoredQuestionAnswerIds: [200, 201],
        );

        $this->assertSame($refreshed, $result);
    }

    public function test_update_contact_attributes_history_entry_records_source_question_answer_ids(): void
    {
        $contact = (new ContactDomainObject)
            ->setId(1)
            ->setAttributes([])
            ->setAttributesHistory([])
            ->setProcessedQuestionAnswerIds([]);

        $refreshed = (new ContactDomainObject)->setId(1);
        $this->contactRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, m::on(function ($data) {
                $history = $data[ContactDomainObject::ATTRIBUTES_HISTORY] ?? [];

                return count($history) === 1
                    && $history[0]['source_question_answer_ids'] === [500, 501];
            }));
        $this->contactRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($refreshed);

        $result = $this->service->updateContactAttributes(
            contact: $contact,
            newAttributes: ['county' => 'walker'],
            changedByUserId: 99,
            sourceQuestionAnswerIds: [500, 501],
        );

        $this->assertSame($refreshed, $result);
    }

    public function test_contact_model_allows_mass_assignment_of_ignored_question_answer_ids(): void
    {
        $model = new \HiEvents\Models\Contact;
        $this->assertContains(
            ContactDomainObject::IGNORED_QUESTION_ANSWER_IDS,
            $model->getFillable(),
            'Contact model must list ignored_question_answer_ids as fillable; otherwise Ignore decisions are silently dropped on write.',
        );
        $this->assertArrayHasKey(
            ContactDomainObject::IGNORED_QUESTION_ANSWER_IDS,
            $model->getCasts(),
            'Contact model must cast ignored_question_answer_ids as array; otherwise the JSONB round-trip is inconsistent.',
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
