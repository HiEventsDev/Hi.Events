<?php

namespace HiEvents\Repository\Eloquent;

use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Models\Question;
use HiEvents\Models\TicketQuestion;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;

class QuestionRepository extends BaseRepository implements QuestionRepositoryInterface
{
    private TicketRepositoryInterface $ticketRepository;

    public function __construct(Application $application, DatabaseManager $db, TicketRepositoryInterface $ticketRepository)
    {
        parent::__construct($application, $db);
        $this->ticketRepository = $ticketRepository;
    }

    protected function getModel(): string
    {
        return Question::class;
    }

    public function getDomainObject(): string
    {
        return QuestionDomainObject::class;
    }

    public function create(array $attributes, array $ticketIds = []): QuestionDomainObject
    {
        /** @var QuestionDomainObject $question */
        $question = parent::create($attributes);

        foreach ($ticketIds as $ticketId) {
            $ticketQuestion = new TicketQuestion();
            $ticketQuestion->create([
                'ticket_id' => $ticketId,
                'question_id' => $question->getId(),
            ]);
        }

        $question->setTickets($this->ticketRepository->findWhereIn('id', $ticketIds));

        return $question;
    }

    public function updateQuestion(int $questionId, int $eventId, array $attributes, array $ticketIds = []): void
    {
        $this->updateWhere($attributes, [
            'id' => $questionId,
            'event_id' => $eventId,
        ]);

        TicketQuestion::where('question_id', $questionId)->delete();

        foreach ($ticketIds as $ticketId) {
            $ticketQuestion = new TicketQuestion();
            $ticketQuestion->create([
                'ticket_id' => $ticketId,
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
