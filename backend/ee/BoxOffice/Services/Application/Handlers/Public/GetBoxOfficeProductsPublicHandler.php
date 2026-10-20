<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeCatalogueDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeProductCatalogueService;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;

class GetBoxOfficeProductsPublicHandler
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly BoxOfficeProductCatalogueService $catalogueService,
    ) {}

    public function handle(BoxOfficeDomainObject $boxOffice, ?int $occurrenceId): BoxOfficeCatalogueDTO
    {
        $questions = $boxOffice->getCollectOrderQuestions()
            ? $this->questionRepository
                ->loadRelation(new Relationship(ProductDomainObject::class))
                ->findWhere([
                    QuestionDomainObjectAbstract::EVENT_ID => $boxOffice->getEventId(),
                    QuestionDomainObjectAbstract::IS_HIDDEN => false,
                ])
                ->sortBy(fn (QuestionDomainObject $question) => $question->getOrder())
                ->values()
            : collect();

        return new BoxOfficeCatalogueDTO(
            products: $this->catalogueService->getSellableProducts($boxOffice, $occurrenceId),
            questions: $questions,
        );
    }
}
