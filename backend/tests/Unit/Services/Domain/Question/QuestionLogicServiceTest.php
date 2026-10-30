<?php

namespace Tests\Unit\Services\Domain\Question;

use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Exceptions\InvalidQuestionLogicException;
use HiEvents\Services\Domain\Question\QuestionLogicService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuestionLogicServiceTest extends TestCase
{
    private QuestionLogicService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new QuestionLogicService;
    }

    public function test_ineligible_answers_make_the_question_required(): void
    {
        $question = $this->question(5, ['Yes', 'No'])
            ->setRequired(false)
            ->setDisqualifyingOptions(['No', 'No'])
            ->setDisqualificationMessage('  Members only  ');

        $this->service->normalize($question);

        $this->assertTrue($question->getRequired());
        $this->assertSame(['No'], $question->getDisqualifyingOptions());
        $this->assertSame('Members only', $question->getDisqualificationMessage());
    }

    public function test_condition_values_are_cleared_without_a_parent(): void
    {
        $question = $this->question(5)->setConditionValues(['Yes'])->setDisqualifyingOptions([]);

        $this->service->normalize($question);

        $this->assertNull($question->getConditionValues());
        $this->assertNull($question->getDisqualifyingOptions());
        $this->assertNull($question->getDisqualificationMessage());
    }

    public function test_a_valid_follow_up_passes(): void
    {
        $parent = $this->question(1, ['Yes', 'No']);
        $followUp = $this->question(2)->setConditionQuestionId(1)->setConditionValues(['Yes']);

        $this->service->validate($followUp, collect([$parent]), collect());

        $this->addToAssertionCount(1);
    }

    public static function invalidLogicProvider(): array
    {
        return [
            'missing parent' => [fn () => [self::makeQuestion(2)->setConditionQuestionId(9)->setConditionValues(['Yes']), []], 'condition_question_id'],
            'depends on itself' => [fn () => [self::makeQuestion(1, ['Yes'])->setConditionQuestionId(1)->setConditionValues(['Yes']), [self::makeQuestion(1, ['Yes'])]], 'condition_question_id'],
            'parent without options' => [fn () => [self::makeQuestion(2)->setConditionQuestionId(1)->setConditionValues(['Yes']), [self::makeQuestion(1)]], 'condition_question_id'],
            'order question on attendee parent' => [fn () => [self::makeQuestion(2)->setConditionQuestionId(1)->setConditionValues(['Yes']), [self::makeQuestion(1, ['Yes'])->setBelongsTo('PRODUCT')]], 'condition_question_id'],
            'no answers chosen' => [fn () => [self::makeQuestion(2)->setConditionQuestionId(1)->setConditionValues([]), [self::makeQuestion(1, ['Yes'])]], 'condition_values'],
            'unknown answer' => [fn () => [self::makeQuestion(2)->setConditionQuestionId(1)->setConditionValues(['Maybe']), [self::makeQuestion(1, ['Yes'])]], 'condition_values'],
            'loop' => [fn () => [self::makeQuestion(1, ['Yes'])->setConditionQuestionId(2)->setConditionValues(['Yes']), [self::makeQuestion(1, ['Yes']), self::makeQuestion(2, ['Yes'])->setConditionQuestionId(1)->setConditionValues(['Yes'])]], 'condition_question_id'],
            'ineligible on text question' => [fn () => [self::makeQuestion(1)->setDisqualifyingOptions(['x'])->setDisqualificationMessage('No'), []], 'disqualifying_options'],
            'ineligible answer not an option' => [fn () => [self::makeQuestion(1, ['Yes', 'No'])->setDisqualifyingOptions(['Maybe'])->setDisqualificationMessage('No'), []], 'disqualifying_options'],
            'every answer ineligible' => [fn () => [self::makeQuestion(1, ['Yes', 'No'])->setDisqualifyingOptions(['Yes', 'No'])->setDisqualificationMessage('No'), []], 'disqualifying_options'],
            'no ineligible message' => [fn () => [self::makeQuestion(1, ['Yes', 'No'])->setDisqualifyingOptions(['No'])->setDisqualificationMessage(' '), []], 'disqualification_message'],
        ];
    }

    #[DataProvider('invalidLogicProvider')]
    public function test_invalid_logic_is_rejected_on_the_right_field(callable $arrange, string $field): void
    {
        [$question, $eventQuestions] = $arrange();

        try {
            $this->service->validate($question, collect($eventQuestions), collect());
            $this->fail('Expected InvalidQuestionLogicException');
        } catch (InvalidQuestionLogicException $exception) {
            $this->assertSame($field, $exception->getField());
        }
    }

    public function test_follow_ups_nested_too_deep_are_rejected(): void
    {
        $chain = [self::makeQuestion(1, ['Yes'])];
        for ($id = 2; $id <= QuestionLogicService::MAX_FOLLOW_UP_DEPTH + 1; $id++) {
            $chain[] = self::makeQuestion($id, ['Yes'])->setConditionQuestionId($id - 1)->setConditionValues(['Yes']);
        }
        $deepest = self::makeQuestion(99)->setConditionQuestionId(QuestionLogicService::MAX_FOLLOW_UP_DEPTH + 1)->setConditionValues(['Yes']);

        $this->expectException(InvalidQuestionLogicException::class);

        $this->service->validate($deepest, collect($chain), collect());
    }

    public function test_moving_a_question_with_follow_ups_under_a_deep_chain_counts_its_follow_ups(): void
    {
        $questions = collect([
            self::makeQuestion(1, ['Yes']),
            self::makeQuestion(2, ['Yes'])->setConditionQuestionId(1)->setConditionValues(['Yes']),
            self::makeQuestion(3, ['Yes'])->setConditionQuestionId(2)->setConditionValues(['Yes']),
            self::makeQuestion(4, ['Yes'])->setConditionQuestionId(3)->setConditionValues(['Yes']),
            self::makeQuestion(10, ['Yes']),
            self::makeQuestion(11, ['Yes'])->setConditionQuestionId(10)->setConditionValues(['Yes']),
            self::makeQuestion(12)->setConditionQuestionId(11)->setConditionValues(['Yes']),
        ]);
        $moved = self::makeQuestion(10, ['Yes'])->setConditionQuestionId(4)->setConditionValues(['Yes']);

        $this->expectException(InvalidQuestionLogicException::class);

        $this->service->validate($moved, $questions, collect());
    }

    public function test_removing_an_option_a_follow_up_uses_is_rejected(): void
    {
        $parent = $this->question(1, ['No']);
        $dependent = $this->question(2)->setConditionQuestionId(1)->setConditionValues(['Yes']);

        $this->expectException(InvalidQuestionLogicException::class);

        $this->service->validate($parent, collect([$parent, $dependent]), collect([$dependent]));
    }

    public function test_a_parent_cannot_become_a_text_question_or_move_to_attendees_while_order_follow_ups_depend_on_it(): void
    {
        $dependent = $this->question(2)->setConditionQuestionId(1)->setConditionValues(['Yes']);

        foreach ([
            'type' => $this->question(1),
            'belongs_to' => $this->question(1, ['Yes'])->setBelongsTo('PRODUCT'),
        ] as $field => $parent) {
            try {
                $this->service->validate($parent, collect([$parent, $dependent]), collect([$dependent]));
                $this->fail("Expected a {$field} error");
            } catch (InvalidQuestionLogicException $exception) {
                $this->assertSame($field, $exception->getField());
            }
        }
    }

    public function test_renames_are_applied_to_follow_up_conditions(): void
    {
        $dependent = $this->question(2)->setConditionQuestionId(1)->setConditionValues(['Other', 'Vegan']);

        $renamed = $this->service->renameConditionValues($dependent, [['from' => 'Other', 'to' => 'Something else']]);

        $this->assertSame(['Something else', 'Vegan'], $renamed->getConditionValues());
        $this->assertSame(['Other', 'Vegan'], $dependent->getConditionValues());
    }

    private function question(int $id, array $options = []): QuestionDomainObject
    {
        return self::makeQuestion($id, $options);
    }

    private static function makeQuestion(int $id, array $options = []): QuestionDomainObject
    {
        return (new QuestionDomainObject)
            ->setId($id)
            ->setTitle("Question {$id}")
            ->setBelongsTo('ORDER')
            ->setType($options === [] ? 'SINGLE_LINE_TEXT' : 'RADIO')
            ->setOptions($options)
            ->setIsHidden(false);
    }
}
