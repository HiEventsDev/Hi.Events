<?php

namespace HiEvents\Validators\Rules;

use HiEvents\DomainObjects\Enums\AttendeeDetailsCollectionMethod;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\QuestionDomainObject;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductQuestionRule extends BaseQuestionRule
{
    private bool $skipBasicAttendeeValidation = false;

    public function __construct(
        Collection $questions,
        Collection $products,
        ?string $attendeeDetailsCollectionMethod = null,
        private readonly string $orderQuestionsPath = 'order.questions',
    ) {
        parent::__construct($questions, $products);

        $this->skipBasicAttendeeValidation = $attendeeDetailsCollectionMethod === AttendeeDetailsCollectionMethod::PER_ORDER->name;
    }

    protected function belongsTo(): QuestionBelongsTo
    {
        return QuestionBelongsTo::PRODUCT;
    }

    private function orderAnswers(): array
    {
        $orderQuestions = Arr::get($this->data, $this->orderQuestionsPath, []);

        return $this->visibility->answersByQuestionId(is_array($orderQuestions) ? $orderQuestions : []);
    }

    private function attendeeAnswers(mixed $productData): array
    {
        $questions = is_array($productData) ? ($productData['questions'] ?? []) : [];

        return $this->visibility->answersByQuestionId(is_array($questions) ? $questions : []);
    }

    /**
     * @throws ValidationException
     */
    protected function validateRequiredQuestionArePresent(Collection $orderProducts): void
    {
        $orderAnswers = $this->orderAnswers();

        foreach ($orderProducts as $productData) {
            if (! isset($productData['product_price_id']) || ! is_numeric($productData['product_price_id'])) {
                throw ValidationException::withMessages([
                    __('This product is outdated. Please reload the page.'),
                ]);
            }

            $productId = $this->getProductIdFromProductPriceId((int) $productData['product_price_id']);
            $questions = $productData['questions'] ?? [];

            $attendeeAnswers = $this->attendeeAnswers($productData);

            $requiredQuestionIds = $this->questions
                ->filter(function (QuestionDomainObject $question) use ($productId, $orderAnswers, $attendeeAnswers) {
                    return $question->getRequired()
                        && ! $question->getIsHidden()
                        && $question->getProducts()?->map(fn ($product) => $product->getId())->contains($productId)
                        && $this->visibility->isConditionMet($question, $this->questionsById, $orderAnswers, $attendeeAnswers, $productId);
                })
                ->map(fn (QuestionDomainObject $question) => $question->getId());

            if (array_diff($requiredQuestionIds->toArray(), collect($questions)->pluck('question_id')->toArray())) {
                throw ValidationException::withMessages([
                    __('Required questions have not been answered. You may need to reload the page.'),
                ]);
            }
        }
    }

    protected function validateQuestions(mixed $products): array
    {
        $validationMessages = [];
        $orderAnswers = $this->orderAnswers();

        foreach ($products as $productIndex => $productRequestData) {
            if (! isset($productRequestData['product_id']) || ! is_numeric($productRequestData['product_id'])) {
                throw ValidationException::withMessages([
                    __('This product is outdated. Please reload the page.'),
                ]);
            }

            $productDomainObject = $this->getProductDomainObject(
                $this->getProductIdFromProductPriceId((int) $productRequestData['product_price_id'])
            );

            if ($productDomainObject->getProductType() === ProductType::TICKET->name && ! $this->skipBasicAttendeeValidation) {
                $validationMessages = [
                    ...$validationMessages,
                    ...$this->validateBasicTicketFields($productRequestData, $productIndex),
                ];
            }

            $questions = $productRequestData['questions'] ?? [];
            $attendeeAnswers = $this->attendeeAnswers($productRequestData);

            foreach ($questions as $questionIndex => $question) {
                $questionDomainObject = $this->getQuestionDomainObject($question['question_id'] ?? null);
                $key = 'products.'.$productIndex.'.questions.'.$questionIndex.'.response';
                $response = empty($question['response']) ? null : $question['response'];
                $answer = $response['answer'] ?? $response;

                if (! $questionDomainObject) {
                    $validationMessages[$key.'.answer'][] = __('This question is outdated. Please reload the page.');

                    continue;
                }

                if (! $this->visibility->isConditionMet(
                    $questionDomainObject,
                    $this->questionsById,
                    $orderAnswers,
                    $attendeeAnswers,
                    $productDomainObject->getId(),
                )) {
                    continue;
                }

                if (is_null($response) && ! $questionDomainObject->getRequired()) {
                    continue;
                }

                if ($questionDomainObject->getRequired()) {
                    $validationMessages = $this->validateRequiredFields($questionDomainObject, $response, $key, $validationMessages);
                }

                if (! $questionDomainObject->isAnswerValid($answer)) {
                    $validationMessages[$key.'.answer'][] = __('Please select an option');
                }

                $validationMessages = $this->validateResponseLength($questionDomainObject, $response, $key, $validationMessages);
                $validationMessages = $this->validateEligibility($questionDomainObject, $answer, $key, $validationMessages);
            }
        }

        return $validationMessages;
    }

    private function validateBasicTicketFields(mixed $productRequestData, int|string $productIndex): array
    {
        $validationMessages = [];

        $validator = Validator::make($productRequestData, [
            'first_name' => ['required', 'string', 'min:1', 'max:100', new PersonNameRule],
            'last_name' => ['required', 'string', 'min:1', 'max:100', new PersonNameRule],
            'email' => ['required', 'string', 'email', 'max:100'],
            'email_confirmation' => ['required', 'string', 'email', 'max:100', 'same:email'],
        ], [
            'email_confirmation.required' => __('Please confirm the email address'),
            'email_confirmation.same' => __('Email addresses do not match'),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $validationMessages["products.$productIndex.$field"][] = $message;
                }
            }
        }

        return $validationMessages;
    }
}
