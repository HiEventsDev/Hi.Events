<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;

class QuestionTicketScopeService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly QuestionRepositoryInterface $questionRepository,
    ) {}

    public function appliesToAllTickets(QuestionDomainObject $question): bool
    {
        return $question->getAppliesToAllTickets()
            && $question->getBelongsTo() === QuestionBelongsTo::PRODUCT->name;
    }

    /**
     * @return int[]
     */
    public function resolveProductIds(QuestionDomainObject $question, array $productIds): array
    {
        if (! $this->appliesToAllTickets($question)) {
            return $productIds;
        }

        return $this->productRepository
            ->findWhere([
                ProductDomainObjectAbstract::EVENT_ID => $question->getEventId(),
                ProductDomainObjectAbstract::PRODUCT_TYPE => ProductType::TICKET->name,
            ])
            ->map(fn (ProductDomainObject $product) => $product->getId())
            ->values()
            ->all();
    }

    public function syncProduct(ProductDomainObject $product): void
    {
        $questionIds = $this->questionRepository
            ->findWhere([
                QuestionDomainObjectAbstract::EVENT_ID => $product->getEventId(),
                QuestionDomainObjectAbstract::BELONGS_TO => QuestionBelongsTo::PRODUCT->name,
                QuestionDomainObjectAbstract::APPLIES_TO_ALL_TICKETS => true,
            ])
            ->map(fn (QuestionDomainObject $question) => $question->getId())
            ->values()
            ->all();

        if ($questionIds === []) {
            return;
        }

        if ($product->getProductType() === ProductType::TICKET->name) {
            $this->questionRepository->attachProductToQuestions($product->getId(), $questionIds);

            return;
        }

        $this->questionRepository->detachProductFromQuestions($product->getId(), $questionIds);
    }
}
