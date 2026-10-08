<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Request;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Enums\AttendeeDetailsCollectionMethod;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Validators\Rules\OrderQuestionRule;
use HiEvents\Validators\Rules\ProductQuestionRule;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class CreateBoxOfficeOrderPublicRequest extends BaseRequest
{
    public function rules(QuestionRepositoryInterface $questionRepository, ProductRepositoryInterface $productRepository): array
    {
        $boxOffice = $this->boxOffice();
        $questions = $boxOffice?->getCollectOrderQuestions()
            ? $questionRepository
                ->loadRelation(new Relationship(ProductDomainObject::class))
                ->findWhere([QuestionDomainObjectAbstract::EVENT_ID => $boxOffice->getEventId()])
            : null;

        return [
            'questions' => $this->orderQuestionRules($questions),
            'attendees' => $this->attendeeRules($questions, $boxOffice, $productRepository),
            'attendees.*.product_id' => ['required', 'integer'],
            'attendees.*.product_price_id' => ['required', 'integer'],
            'attendees.*.questions' => ['nullable', 'array'],
            'attendees.*.questions.*.question_id' => ['required', 'integer'],
            'attendees.*.questions.*.response' => ['nullable'],
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.product_price_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.override_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'items.*.seat_uids' => ['sometimes', 'array', 'max:100'],
            'items.*.seat_uids.*' => ['required', 'string', 'max:24'],
            'discount' => ['nullable', 'array'],
            'discount.type' => ['required_with:discount', Rule::in(PromoCodeDiscountTypeEnum::FIXED->name, PromoCodeDiscountTypeEnum::PERCENTAGE->name)],
            'discount.value' => ['required_with:discount', 'numeric', 'min:0', 'max:999999.99'],
            'buyer' => ['nullable', 'array'],
            'buyer.first_name' => ['nullable', 'string', 'max:50'],
            'buyer.last_name' => ['nullable', 'string', 'max:50'],
            'buyer.email' => ['nullable', 'email', 'max:255'],
            'questions.*.question_id' => ['required', 'integer'],
            'questions.*.response' => ['nullable'],
        ];
    }

    private function boxOffice(): ?BoxOfficeDomainObject
    {
        return $this->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE);
    }

    private function orderQuestionRules(?Collection $questions): array
    {
        if ($questions === null) {
            return ['nullable', 'array'];
        }

        $orderQuestions = $questions->filter(
            fn (QuestionDomainObject $question) => $question->getBelongsTo() === QuestionBelongsTo::ORDER->name
        );

        return ['present', 'array', new OrderQuestionRule($orderQuestions, collect())];
    }

    private function attendeeRules(?Collection $questions, ?BoxOfficeDomainObject $boxOffice, ProductRepositoryInterface $productRepository): array
    {
        if ($questions === null || $boxOffice === null) {
            return ['nullable', 'array'];
        }

        $productQuestions = $questions->filter(
            fn (QuestionDomainObject $question) => $question->getBelongsTo() === QuestionBelongsTo::PRODUCT->name
        );

        $products = $productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->findWhere([ProductDomainObjectAbstract::EVENT_ID => $boxOffice->getEventId()]);

        return [
            'present',
            'array',
            new ProductQuestionRule($productQuestions, $products, AttendeeDetailsCollectionMethod::PER_ORDER->name),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->sometimes('attendees', function ($attribute, $value, $fail) {
            $expected = collect($this->input('items', []))->sum(fn ($item) => (int) ($item['quantity'] ?? 0));

            if (count($value ?? []) !== $expected) {
                $fail(__('Attendee details are out of step with the cart. Please reload and try again.'));
            }
        }, fn () => $this->boxOffice()?->getCollectOrderQuestions() === true);

        $validator->sometimes('discount.value', 'max:100', function ($input) {
            return ($input->discount['type'] ?? null) === PromoCodeDiscountTypeEnum::PERCENTAGE->name;
        });
    }
}
