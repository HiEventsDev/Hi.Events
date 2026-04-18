<?php

namespace Tests\Unit\Services\Domain\Question;

use HiEvents\DomainObjects\ContactAttributeDefinitionDomainObject;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Repository\Interfaces\ContactAttributeDefinitionRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Question\QuestionContactAttributeLinker;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Mockery as m;
use Tests\TestCase;

class QuestionContactAttributeLinkerTest extends TestCase
{
    private ContactAttributeDefinitionRepositoryInterface $definitionRepository;

    private EventRepositoryInterface $eventRepository;

    private QuestionContactAttributeLinker $linker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->definitionRepository = m::mock(ContactAttributeDefinitionRepositoryInterface::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->linker = new QuestionContactAttributeLinker(
            $this->definitionRepository,
            $this->eventRepository,
        );
    }

    public function test_null_definition_id_is_no_op(): void
    {
        $this->definitionRepository->shouldNotReceive('findById');
        $this->eventRepository->shouldNotReceive('findById');

        $this->linker->validate(10, null, QuestionTypeEnum::SINGLE_LINE_TEXT);

        $this->assertTrue(true);
    }

    public function test_valid_link_passes_with_matching_account_and_compatible_type(): void
    {
        $this->definitionRepository
            ->shouldReceive('findById')
            ->with(5)
            ->andReturn(
                (new ContactAttributeDefinitionDomainObject)
                    ->setId(5)
                    ->setAccountId(42)
                    ->setName('company')
                    ->setLabel('Company')
                    ->setType('text')
            );

        $this->eventRepository
            ->shouldReceive('findById')
            ->with(10)
            ->andReturn(
                (new EventDomainObject)->setId(10)->setAccountId(42)
            );

        $this->linker->validate(10, 5, QuestionTypeEnum::SINGLE_LINE_TEXT);

        $this->assertTrue(true);
    }

    public function test_missing_definition_throws(): void
    {
        $this->definitionRepository
            ->shouldReceive('findById')
            ->with(999)
            ->andThrow(new ModelNotFoundException);

        $this->expectException(ValidationException::class);

        $this->linker->validate(10, 999, QuestionTypeEnum::SINGLE_LINE_TEXT);
    }

    public function test_cross_account_definition_throws(): void
    {
        $this->definitionRepository
            ->shouldReceive('findById')
            ->with(5)
            ->andReturn(
                (new ContactAttributeDefinitionDomainObject)
                    ->setId(5)
                    ->setAccountId(99)
                    ->setName('company')
                    ->setLabel('Company')
                    ->setType('text')
            );

        $this->eventRepository
            ->shouldReceive('findById')
            ->with(10)
            ->andReturn(
                (new EventDomainObject)->setId(10)->setAccountId(42)
            );

        $this->expectException(ValidationException::class);

        $this->linker->validate(10, 5, QuestionTypeEnum::SINGLE_LINE_TEXT);
    }

    public function test_incompatible_type_throws(): void
    {
        $this->definitionRepository
            ->shouldReceive('findById')
            ->with(5)
            ->andReturn(
                (new ContactAttributeDefinitionDomainObject)
                    ->setId(5)
                    ->setAccountId(42)
                    ->setName('interests')
                    ->setLabel('Interests')
                    ->setType('multi_select')
            );

        $this->eventRepository
            ->shouldReceive('findById')
            ->with(10)
            ->andReturn(
                (new EventDomainObject)->setId(10)->setAccountId(42)
            );

        $this->expectException(ValidationException::class);

        $this->linker->validate(10, 5, QuestionTypeEnum::SINGLE_LINE_TEXT);
    }

    public function test_text_definition_compatible_with_scalar_question_types(): void
    {
        $scalarTypes = [
            QuestionTypeEnum::SINGLE_LINE_TEXT,
            QuestionTypeEnum::MULTI_LINE_TEXT,
            QuestionTypeEnum::ADDRESS,
            QuestionTypeEnum::PHONE,
            QuestionTypeEnum::DATE,
            QuestionTypeEnum::RADIO,
            QuestionTypeEnum::DROPDOWN,
        ];

        foreach ($scalarTypes as $type) {
            $this->assertTrue(
                QuestionContactAttributeLinker::isCompatible($type, 'text'),
                "text definition should accept $type->name"
            );
        }
    }

    public function test_text_definition_incompatible_with_multi_value_question_types(): void
    {
        $this->assertFalse(
            QuestionContactAttributeLinker::isCompatible(QuestionTypeEnum::CHECKBOX, 'text')
        );
        $this->assertFalse(
            QuestionContactAttributeLinker::isCompatible(QuestionTypeEnum::MULTI_SELECT_DROPDOWN, 'text')
        );
    }

    public function test_select_definition_compatibility_matrix(): void
    {
        $this->assertTrue(
            QuestionContactAttributeLinker::isCompatible(QuestionTypeEnum::RADIO, 'select')
        );
        $this->assertTrue(
            QuestionContactAttributeLinker::isCompatible(QuestionTypeEnum::DROPDOWN, 'select')
        );
        $this->assertFalse(
            QuestionContactAttributeLinker::isCompatible(QuestionTypeEnum::CHECKBOX, 'select')
        );
        $this->assertFalse(
            QuestionContactAttributeLinker::isCompatible(QuestionTypeEnum::SINGLE_LINE_TEXT, 'select')
        );
    }

    public function test_multi_select_definition_compatibility_matrix(): void
    {
        $this->assertTrue(
            QuestionContactAttributeLinker::isCompatible(QuestionTypeEnum::CHECKBOX, 'multi_select')
        );
        $this->assertTrue(
            QuestionContactAttributeLinker::isCompatible(QuestionTypeEnum::MULTI_SELECT_DROPDOWN, 'multi_select')
        );
        $this->assertFalse(
            QuestionContactAttributeLinker::isCompatible(QuestionTypeEnum::RADIO, 'multi_select')
        );
        $this->assertFalse(
            QuestionContactAttributeLinker::isCompatible(QuestionTypeEnum::SINGLE_LINE_TEXT, 'multi_select')
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
