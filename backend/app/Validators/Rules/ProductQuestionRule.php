<?php

namespace HiEvents\Validators\Rules;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\QuestionDomainObject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductQuestionRule extends BaseQuestionRule
{
    /**
     * @throws ValidationException
     */
    protected function validateRequiredQuestionArePresent(Collection $orderProducts): void
    {
        foreach ($orderProducts as $productData) {
            $productId = $this->getProductIdFromProductPriceId($productData['product_price_id']);
            $questions = $productData['questions'] ?? [];

            $requiredQuestionIds = $this->questions
                ->filter(function (QuestionDomainObject $question) use ($productId) {
                    return $question->getRequired()
                        && !$question->getIsHidden()
                        && $question->getProducts()?->map(fn($product) => $product->getId())->contains($productId);
                })
                ->map(fn(QuestionDomainObject $question) => $question->getId());

            if (array_diff($requiredQuestionIds->toArray(), collect($questions)->pluck('question_id')->toArray())) {
                throw ValidationException::withMessages([
                    __('Required questions have not been answered. You may need to reload the page.')
                ]);
            }
        }
    }

    protected function validateQuestions(mixed $products): array
    {
        $validationMessages = [];

        foreach ($products as $productIndex => $productRequestData) {
            $productDomainObject = $this->getProductDomainObject($productRequestData['product_id']);

            if (!$productDomainObject) {
                $validationMessages['products.' . $productIndex][] = __('This product is outdated. Please reload the page.');
                continue;
            }

            if ($productDomainObject->getProductType() === ProductType::TICKET->name) {
                $validationMessages = [
                    ...$validationMessages,
                    ...$this->validateBasicTicketFields($productRequestData, $productIndex),
                ];
            }

            $questions = $productRequestData['questions'] ?? [];
            foreach ($questions as $questionIndex => $question) {
                $questionDomainObject = $this->getQuestionDomainObject($question['question_id'] ?? null);
                $key = 'products.' . $productIndex . '.questions.' . $questionIndex . '.response';
                $response = empty($question['response']) ? null : $question['response'];
                $answer = $response['answer'] ?? $response;

                if (!$questionDomainObject) {
                    $validationMessages[$key . '.answer'][] = __('This question is outdated. Please reload the page.');
                    continue;
                }

                if (is_null($response) && !$questionDomainObject->getRequired()) {
                    continue;
                }

                if ($questionDomainObject->getRequired()) {
                    $validationMessages = $this->validateRequiredFields($questionDomainObject, $response, $key, $validationMessages);
                }

                if (!$questionDomainObject->isAnswerValid($answer)) {
                    $validationMessages[$key . '.answer'][] = __('Please select an option');
                }

                $validationMessages = $this->validateResponseLength($questionDomainObject, $response, $key, $validationMessages);
            }
        }

        return $validationMessages;
    }

    private function validateBasicTicketFields(mixed $productRequestData, int|string $productIndex): array
    {
        $validationMessages = [];

        $validator = Validator::make($productRequestData, [
            'first_name' => ['required', 'string', 'min:1', 'max:100'],
            'last_name' => ['required', 'string', 'min:1', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:100'],
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
