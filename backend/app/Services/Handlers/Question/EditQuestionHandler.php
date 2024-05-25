<?php

namespace HiEvents\Services\Handlers\Question;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Repository\Eloquent\QuestionRepository;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Handlers\Question\DTO\UpsertQuestionDTO;
use Illuminate\Support\Facades\DB;

readonly class EditQuestionHandler
{
    private QuestionRepository $questionRepository;

    public function __construct(QuestionRepositoryInterface $questionRepository)
    {
        $this->questionRepository = $questionRepository;
    }

    public function handle(int $questionId, UpsertQuestionDTO $createQuestionDTO): QuestionDomainObject
    {
        return DB::transaction(function () use ($questionId, $createQuestionDTO) {
            $this->questionRepository->updateQuestion($questionId, $createQuestionDTO->event_id, [
                QuestionDomainObjectAbstract::TITLE => $createQuestionDTO->title,
                QuestionDomainObjectAbstract::BELONGS_TO => $createQuestionDTO->belongs_to->name,
                QuestionDomainObjectAbstract::TYPE => $createQuestionDTO->type->name,
                QuestionDomainObjectAbstract::REQUIRED => $createQuestionDTO->required,
                QuestionDomainObjectAbstract::OPTIONS => $createQuestionDTO->options,
                QuestionDomainObjectAbstract::IS_HIDDEN => $createQuestionDTO->is_hidden

            ], $createQuestionDTO->ticket_ids);

            return $this->questionRepository
                ->loadRelation(TicketDomainObject::class)
                ->findById($questionId);
        });
    }
}
