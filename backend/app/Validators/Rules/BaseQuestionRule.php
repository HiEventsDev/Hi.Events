<?php

namespace HiEvents\Validators\Rules;

use Closure;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

abstract class BaseQuestionRule implements ValidationRule, DataAwareRule, ValidatorAwareRule
{
    protected const ADDRESS_FIELDS = [
        'address_line_1',
        'address_line_2',
        'city',
        'state_or_region',
        'zip_or_postal_code',
        'country',
    ];

    protected const ADDRESS_REQUIRED_FIELDS = [
        'address_line_1',
        'city',
        'state_or_region',
        'zip_or_postal_code',
        'country',
    ];

    protected Collection $questions;

    private Collection $products;

    protected Validator $validator;

    protected array $data = [];

    abstract protected function validateRequiredQuestionArePresent(Collection $data): void;

    abstract protected function validateQuestions(mixed $data): array;

    public function __construct(Collection $questions, Collection $products)
    {
        $this->questions = $questions;
        $this->products = $products;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->validateRequiredQuestionArePresent(collect($value));

        $questionValidationMessages = $this->validateQuestions($value);

        if ($questionValidationMessages) {
            $this->validator->messages()->merge($questionValidationMessages);
        }
    }

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    protected function getProductIdFromProductPriceId(int $productPriceId): int
    {
        $productPrices = new Collection();
        $this->products->each(fn(ProductDomainObject $product) => $productPrices->push(...$product->getProductPrices()));

        /** @var ProductPriceDomainObject $productPrice */
        $productPrice = $productPrices
            ->first(fn(ProductPriceDomainObject $productPrice) => $productPrice->getId() === $productPriceId);

        return $productPrice->getProductId();
    }

    protected function getQuestionDomainObject(?int $questionId): ?QuestionDomainObject
    {
        if ($questionId === null) {
            return null;
        }

        return $this->questions->filter(fn($question) => $question->getId() === $questionId)?->first();
    }

    protected function validateRequiredFields(
        QuestionDomainObject $questionDomainObject,
        mixed                $response,
        string               $key,
        array                $validationMessages
    ): array
    {
        if ($questionDomainObject->getType() === QuestionTypeEnum::ADDRESS->name) {
            foreach (self::ADDRESS_REQUIRED_FIELDS as $field) {
                if (empty($response[$field])) {
                    $validationMessages[$key . '.' . $field][] = __('This field is required.');
                }
            }

            return $validationMessages;
        }

        if (empty($response) || (is_array($response) && empty($response['answer']))) {
            $validationMessages[$key . '.answer'][] = 'This field is required.';
        }

        return $validationMessages;
    }

    protected function validateResponseLength(
        QuestionDomainObject $questionDomainObject,
        mixed                $response,
        string               $key,
        array                $validationMessages
    ): array
    {
        if ($questionDomainObject->getType() === QuestionTypeEnum::ADDRESS->name) {
            foreach (self::ADDRESS_FIELDS as $field) {
                if (isset($response[$field]) && strlen($response[$field]) > 255) {
                    $validationMessages[$key . '.' . $field][] = __('This field must be less than 255 characters.');
                } elseif (isset($response[$field]) && strlen($response[$field]) < 2) {
                    $validationMessages[$key . '.' . $field][] = __('This field must be at least 2 characters.');
                }
            }

            return $validationMessages;
        }

        if (isset($response['answer']) && !is_array($response['answer']) && strlen($response['answer']) > 255) {
            $validationMessages[$key . '.answer'][] = __('This field must be less than 255 characters.');
        }

        return $validationMessages;
    }

    protected function getProductDomainObject(int $id): ?ProductDomainObject
    {
        return $this->products->filter(fn($product) => $product->getId() === $id)?->first();
    }
}
