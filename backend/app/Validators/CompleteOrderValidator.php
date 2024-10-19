<?php

declare(strict_types=1);

namespace HiEvents\Validators;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Validators\Rules\OrderQuestionRule;
use HiEvents\Validators\Rules\ProductQuestionRule;
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
            'products' => new ProductQuestionRule($productQuestions, $products),
        ];
    }

    public function messages(): array
    {
        return [
            'order.first_name' => __('First name is required'),
            'order.last_name' => __('Last name is required'),
            'order.email' => __('A valid email is required'),
        ];
    }
}
