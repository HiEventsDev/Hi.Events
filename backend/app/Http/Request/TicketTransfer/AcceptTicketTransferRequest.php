<?php

namespace HiEvents\Http\Request\TicketTransfer;

use HiEvents\DomainObjects\Enums\AttendeeDetailsCollectionMethod;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferClaimService;
use HiEvents\Validators\Rules\PersonNameRule;
use HiEvents\Validators\Rules\ProductQuestionRule;

class AcceptTicketTransferRequest extends BaseRequest
{
    /**
     * @throws ResourceNotFoundException
     */
    public function rules(): array
    {
        $transfer = app(TicketTransferClaimService::class)->findByToken((string) $this->route('token'));

        $questions = app(QuestionRepositoryInterface::class)
            ->loadRelation(new Relationship(ProductDomainObject::class, [
                new Relationship(ProductPriceDomainObject::class),
            ]))
            ->findWhere([QuestionDomainObjectAbstract::EVENT_ID => $transfer->getEventId()]);

        $products = app(ProductRepositoryInterface::class)
            ->loadRelation(ProductPriceDomainObject::class)
            ->findWhere([ProductDomainObjectAbstract::EVENT_ID => $transfer->getEventId()]);

        return [
            'first_name' => ['required', 'string', 'max:40', new PersonNameRule],
            'last_name' => ['required', 'string', 'max:40', new PersonNameRule],
            'questions' => ['array'],
            'products' => new ProductQuestionRule($questions, $products, AttendeeDetailsCollectionMethod::PER_ORDER->name),
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => __('First name is required'),
            'last_name.required' => __('Last name is required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $transfer = app(TicketTransferClaimService::class)->findByToken((string) $this->route('token'));
        $attendee = app(AttendeeRepositoryInterface::class)->findById($transfer->getAttendeeId());

        $this->merge([
            'products' => [[
                'product_id' => $attendee->getProductId(),
                'product_price_id' => $attendee->getProductPriceId(),
                'questions' => $this->input('questions', []),
            ]],
        ]);
    }
}
