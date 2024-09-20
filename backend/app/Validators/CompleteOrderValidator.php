<?php

declare(strict_types=1);

namespace HiEvents\Validators;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Validators\Rules\AttendeeQuestionRule;
use HiEvents\Validators\Rules\OrderQuestionRule;
use Illuminate\Routing\Route;

class CompleteOrderValidator extends BaseValidator
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly ProductRepositoryInterface  $productRepository,
        private readonly Route                       $route
    )
    {
    }

    public function rules(): array
    {
        $questions = $this->questionRepository
            ->loadRelation(
                new Relationship(ProductDomainObject::class, [
                    new Relationship(ProductPriceDomainObject::class)
                ])
            )
            ->findWhere(
                [QuestionDomainObjectAbstract::EVENT_ID => $this->route->parameter('event_id')]
            );
        $orderQuestions = $questions->filter(
            fn(QuestionDomainObject $question) => $question->getBelongsTo() === QuestionBelongsTo::ORDER->name
        );
        $productQuestions = $questions->filter(
            fn(QuestionDomainObject $question) => $question->getBelongsTo() === QuestionBelongsTo::PRODUCT->name
        );

        $products = $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->findWhere(
                [ProductDomainObjectAbstract::EVENT_ID => $this->route->parameter('event_id')]
            );

        return [
            'order.first_name' => ['required', 'string', 'max:40'],
            'order.last_name' => ['required', 'string', 'max:40'],
            'order.questions' => new OrderQuestionRule($orderQuestions, $products),
            'order.email' => 'required|email',
            'attendees.*.first_name' => ['required', 'string', 'max:40'],
            'attendees.*.last_name' => ['required', 'string', 'max:40'],
            'attendees.*.email' => ['required', 'email'],
            'attendees' => new AttendeeQuestionRule($productQuestions, $products),

            // Address validation is intentionally not strict, as we want to support all countries
            'order.address.address_line_1' => ['string', 'max:255'],
            'order.address.address_line_2' => ['string', 'max:255', 'nullable'],
            'order.address.city' => ['string', 'max:85'],
            'order.address.state_or_region' => ['string', 'max:85'],
            'order.address.zip_or_postal_code' => ['string', 'max:85'],
            'order.address.country' => ['string', 'max:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'order.first_name' => __('First name is required'),
            'order.last_name' => __('Last name is required'),
            'order.email' => __('A valid email is required'),
            'attendees.*.first_name' => __('First name is required'),
            'attendees.*.last_name' => __('Last name is required'),
            'attendees.*.email' => __('A valid email is required'),
        ];
    }
}
