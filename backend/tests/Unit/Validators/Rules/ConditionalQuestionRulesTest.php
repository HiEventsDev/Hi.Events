<?php

namespace Tests\Unit\Validators\Rules;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Validators\Rules\OrderQuestionRule;
use HiEvents\Validators\Rules\ProductQuestionRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConditionalQuestionRulesTest extends TestCase
{
    public function test_a_required_follow_up_is_not_required_when_its_condition_is_not_met(): void
    {
        $validator = $this->orderValidator([
            ['question_id' => 1, 'response' => ['answer' => 'No']],
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->all()));
    }

    public function test_a_required_follow_up_is_required_when_its_condition_is_met(): void
    {
        $this->expectException(ValidationException::class);

        $this->orderValidator([
            ['question_id' => 1, 'response' => ['answer' => 'Yes']],
        ])->validate();
    }

    public function test_an_unmet_follow_up_answer_is_not_validated(): void
    {
        $validator = $this->orderValidator([
            ['question_id' => 1, 'response' => ['answer' => 'No']],
            ['question_id' => 2, 'response' => ['answer' => str_repeat('x', 300)]],
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->all()));
    }

    public function test_an_ineligible_answer_fails_with_the_organizer_message(): void
    {
        $validator = $this->orderValidator([
            ['question_id' => 1, 'response' => ['answer' => 'Yes']],
            ['question_id' => 2, 'response' => ['answer' => 'Allergy']],
            ['question_id' => 3, 'response' => ['answer' => 'No']],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            ['This event is for members only.'],
            $validator->errors()->get('order.questions.2.response.answer'),
        );
    }

    public function test_attendee_follow_ups_read_order_answers_from_the_configured_path(): void
    {
        $orderParent = $this->question(1, 'ORDER', ['Yes', 'No']);
        $attendeeFollowUp = $this->question(5, 'PRODUCT')
            ->setRequired(true)
            ->setConditionQuestionId(1)
            ->setConditionValues(['Yes'])
            ->setProducts(collect([(new ProductDomainObject)->setId(10)]));

        $rule = new ProductQuestionRule(
            collect([$orderParent, $attendeeFollowUp]),
            collect([$this->product()]),
            'PER_ORDER',
            'questions',
        );

        $answeredNo = Validator::make([
            'questions' => [['question_id' => 1, 'response' => ['answer' => 'No']]],
            'attendees' => [['product_id' => 10, 'product_price_id' => 100, 'questions' => []]],
        ], ['attendees' => [$rule]]);

        $this->assertFalse($answeredNo->fails(), json_encode($answeredNo->errors()->all()));

        $this->expectException(ValidationException::class);

        Validator::make([
            'questions' => [['question_id' => 1, 'response' => ['answer' => 'Yes']]],
            'attendees' => [['product_id' => 10, 'product_price_id' => 100, 'questions' => []]],
        ], ['attendees' => [new ProductQuestionRule(
            collect([$orderParent, $attendeeFollowUp]),
            collect([$this->product()]),
            'PER_ORDER',
            'questions',
        )]])->validate();
    }

    public function test_omitting_order_questions_still_enforces_required_questions(): void
    {
        $questions = collect([
            $this->question(4, 'ORDER')->setRequired(true),
        ]);

        $validator = Validator::make(
            ['order' => ['first_name' => 'Ada']],
            ['order.questions' => [new OrderQuestionRule($questions, new Collection)]],
        );

        $this->expectException(ValidationException::class);

        $validator->validate();
    }

    public function test_omitting_order_questions_is_fine_when_none_are_required(): void
    {
        $questions = collect([
            $this->question(4, 'ORDER'),
        ]);

        $validator = Validator::make(
            ['order' => ['first_name' => 'Ada']],
            ['order.questions' => [new OrderQuestionRule($questions, new Collection)]],
        );

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->all()));
    }

    public function test_attendee_answers_are_validated_against_the_product_of_the_chosen_price(): void
    {
        $ticketProducts = collect([(new ProductDomainObject)->setId(10)]);
        $parent = $this->question(5, 'PRODUCT', ['Yes', 'No'])->setProducts($ticketProducts);
        $followUp = $this->question(6, 'PRODUCT', ['Yes', 'No'])
            ->setRequired(true)
            ->setConditionQuestionId(5)
            ->setConditionValues(['Yes'])
            ->setDisqualifyingOptions(['Yes'])
            ->setDisqualificationMessage('Not eligible.')
            ->setProducts($ticketProducts);
        $otherProduct = (new ProductDomainObject)
            ->setId(20)
            ->setProductType('GENERAL')
            ->setProductPrices(collect([(new ProductPriceDomainObject)->setId(200)->setProductId(20)]));

        $validator = Validator::make([
            'products' => [[
                'product_id' => 20,
                'product_price_id' => 100,
                'questions' => [
                    ['question_id' => 5, 'response' => ['answer' => 'Yes']],
                    ['question_id' => 6, 'response' => ['answer' => 'Yes']],
                ],
            ]],
        ], ['products' => [new ProductQuestionRule(
            collect([$parent, $followUp]),
            collect([$this->product(), $otherProduct]),
            'PER_ORDER',
        )]]);

        $this->assertTrue($validator->fails());
        $this->assertSame(['Not eligible.'], $validator->errors()->get('products.0.questions.1.response.answer'));
    }

    private function orderValidator(array $answers): \Illuminate\Validation\Validator
    {
        $questions = collect([
            $this->question(1, 'ORDER', ['Yes', 'No']),
            $this->question(2, 'ORDER')->setRequired(true)->setConditionQuestionId(1)->setConditionValues(['Yes']),
            $this->question(3, 'ORDER', ['Yes', 'No'])
                ->setDisqualifyingOptions(['No'])
                ->setDisqualificationMessage('This event is for members only.'),
        ]);

        return Validator::make(
            ['order' => ['questions' => $answers]],
            ['order.questions' => [new OrderQuestionRule($questions, new Collection)]],
        );
    }

    private function question(int $id, string $belongsTo, array $options = []): QuestionDomainObject
    {
        return (new QuestionDomainObject)
            ->setId($id)
            ->setBelongsTo($belongsTo)
            ->setType($options === [] ? 'SINGLE_LINE_TEXT' : 'RADIO')
            ->setOptions($options)
            ->setRequired(false)
            ->setIsHidden(false);
    }

    private function product(): ProductDomainObject
    {
        return (new ProductDomainObject)
            ->setId(10)
            ->setProductType('TICKET')
            ->setProductPrices(collect([(new ProductPriceDomainObject)->setId(100)->setProductId(10)]));
    }
}
