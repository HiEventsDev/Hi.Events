<?php

namespace Tests\Unit\Services\Domain\Question;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Services\Domain\Question\QuestionVisibilityService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class QuestionVisibilityServiceTest extends TestCase
{
    private QuestionVisibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new QuestionVisibilityService;
    }

    public function test_a_question_without_a_condition_is_always_shown(): void
    {
        $question = $this->orderQuestion(1);

        $this->assertTrue($this->service->isConditionMet($question, $this->byId([$question]), []));
    }

    public function test_a_follow_up_is_shown_only_when_the_parent_answer_matches(): void
    {
        $parent = $this->orderQuestion(1, ['Yes', 'No']);
        $followUp = $this->orderQuestion(2)->setConditionQuestionId(1)->setConditionValues(['Yes']);
        $questions = $this->byId([$parent, $followUp]);

        $this->assertTrue($this->service->isConditionMet($followUp, $questions, [1 => 'Yes']));
        $this->assertFalse($this->service->isConditionMet($followUp, $questions, [1 => 'No']));
        $this->assertFalse($this->service->isConditionMet($followUp, $questions, []));
    }

    public function test_any_selected_checkbox_option_can_show_a_follow_up(): void
    {
        $parent = $this->orderQuestion(1, ['Vegan', 'Other'], 'CHECKBOX');
        $followUp = $this->orderQuestion(2)->setConditionQuestionId(1)->setConditionValues(['Other']);

        $this->assertTrue($this->service->isConditionMet($followUp, $this->byId([$parent, $followUp]), [1 => ['Vegan', 'Other']]));
    }

    public function test_a_chain_is_hidden_when_an_earlier_condition_fails(): void
    {
        $first = $this->orderQuestion(1, ['Yes', 'No']);
        $second = $this->orderQuestion(2, ['A', 'B'])->setConditionQuestionId(1)->setConditionValues(['Yes']);
        $third = $this->orderQuestion(3)->setConditionQuestionId(2)->setConditionValues(['A']);
        $questions = $this->byId([$first, $second, $third]);

        $this->assertTrue($this->service->isConditionMet($third, $questions, [1 => 'Yes', 2 => 'A']));
        $this->assertFalse($this->service->isConditionMet($third, $questions, [1 => 'No', 2 => 'A']));
    }

    public function test_a_follow_up_of_a_hidden_or_missing_parent_is_not_shown(): void
    {
        $hiddenParent = $this->orderQuestion(1, ['Yes'])->setIsHidden(true);
        $followUp = $this->orderQuestion(2)->setConditionQuestionId(1)->setConditionValues(['Yes']);
        $orphan = $this->orderQuestion(3)->setConditionQuestionId(99)->setConditionValues(['Yes']);
        $questions = $this->byId([$hiddenParent, $followUp, $orphan]);

        $this->assertFalse($this->service->isConditionMet($followUp, $questions, [1 => 'Yes']));
        $this->assertFalse($this->service->isConditionMet($orphan, $questions, [99 => 'Yes']));
    }

    public function test_attendee_follow_ups_use_the_attendee_answers_and_the_parent_must_apply_to_the_ticket(): void
    {
        $parent = $this->attendeeQuestion(1, ['Yes', 'No'], [10]);
        $followUp = $this->attendeeQuestion(2, [], [10, 11])->setConditionQuestionId(1)->setConditionValues(['Yes']);
        $questions = $this->byId([$parent, $followUp]);

        $this->assertTrue($this->service->isConditionMet($followUp, $questions, [1 => 'No'], [1 => 'Yes'], 10));
        $this->assertFalse($this->service->isConditionMet($followUp, $questions, [], [1 => 'No'], 10));
        $this->assertFalse($this->service->isConditionMet($followUp, $questions, [], [1 => 'Yes'], 11));
    }

    public function test_attendee_follow_ups_can_depend_on_order_questions(): void
    {
        $parent = $this->orderQuestion(1, ['Yes', 'No']);
        $followUp = $this->attendeeQuestion(2, [], [10])->setConditionQuestionId(1)->setConditionValues(['Yes']);

        $this->assertTrue($this->service->isConditionMet($followUp, $this->byId([$parent, $followUp]), [1 => 'Yes'], [], 10));
    }

    public function test_answers_are_read_from_question_entries(): void
    {
        $this->assertSame(
            [1 => 'Yes', 2 => ['A', 'B'], 3 => ['address_line_1' => 'Main St']],
            $this->service->answersByQuestionId([
                ['question_id' => 1, 'response' => ['answer' => 'Yes']],
                ['question_id' => '2', 'response' => ['answer' => ['A', 'B']]],
                ['question_id' => 3, 'response' => ['address_line_1' => 'Main St']],
                ['response' => ['answer' => 'ignored']],
            ]),
        );
    }

    public function test_answers_to_unknown_questions_are_kept(): void
    {
        $this->assertTrue($this->service->shouldKeepAnswer(42, collect(), []));
    }

    private function byId(array $questions): Collection
    {
        return $this->service->keyById(collect($questions));
    }

    private function orderQuestion(int $id, array $options = [], string $type = 'RADIO'): QuestionDomainObject
    {
        return (new QuestionDomainObject)
            ->setId($id)
            ->setBelongsTo('ORDER')
            ->setType($options === [] ? 'SINGLE_LINE_TEXT' : $type)
            ->setOptions($options);
    }

    private function attendeeQuestion(int $id, array $options, array $productIds): QuestionDomainObject
    {
        return $this->orderQuestion($id, $options)
            ->setBelongsTo('PRODUCT')
            ->setProducts(collect(array_map(fn (int $productId) => (new ProductDomainObject)->setId($productId), $productIds)));
    }
}
