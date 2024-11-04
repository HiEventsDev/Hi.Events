<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Models\Question;
use HiEvents\Models\ProductQuestion;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;

class QuestionRepository extends BaseRepository implements QuestionRepositoryInterface
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(Application $application, DatabaseManager $db, ProductRepositoryInterface $productRepository)
    {
        parent::__construct($application, $db);
        $this->productRepository = $productRepository;
    }

    protected function getModel(): string
    {
        return Question::class;
    }

    public function getDomainObject(): string
    {
        return QuestionDomainObject::class;
    }

    public function create(array $attributes, array $productIds = []): QuestionDomainObject
    {
        /** @var QuestionDomainObject $question */
        $question = parent::create($attributes);

        foreach ($productIds as $productId) {
            $productQuestion = new ProductQuestion();
            $productQuestion->create([
                'product_id' => $productId,
                'question_id' => $question->getId(),
            ]);
        }

        $question->setProducts($this->productRepository->findWhereIn('id', $productIds));

        return $question;
    }

    public function updateQuestion(int $questionId, int $eventId, array $attributes, array $productIds = []): void
    {
        $this->updateWhere($attributes, [
            'id' => $questionId,
            'event_id' => $eventId,
        ]);

        ProductQuestion::where('question_id', $questionId)->delete();

        foreach ($productIds as $productId) {
            $productQuestion = new ProductQuestion();
            $productQuestion->create([
                'product_id' => $productId,
                'question_id' => $questionId,
            ]);
        }
    }

    public function findByEventId(int $eventId): Collection
    {
        return $this
            ->findWhere([
                QuestionDomainObjectAbstract::EVENT_ID => $eventId,
            ])->sortBy((fn(QuestionDomainObject $question) => $question->getOrder()));
    }

    public function sortQuestions(int $eventId, array $orderedQuestionIds): void
    {
        $parameters = [
            'eventId' => $eventId,
            'questionIds' => '{' . implode(',', $orderedQuestionIds) . '}',
            'orders' => '{' . implode(',', range(1, count($orderedQuestionIds))) . '}',
        ];

        $query = "WITH new_order AS (
                  SELECT unnest(:questionIds::bigint[]) AS question_id,
                         unnest(:orders::int[]) AS order
              )
              UPDATE questions
              SET \"order\" = new_order.order
              FROM new_order
              WHERE questions.id = new_order.question_id AND questions.event_id = :eventId";

        $this->db->update($query, $parameters);
    }
}
