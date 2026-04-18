<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\ContactRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Contact\DTO\ContactLookupResultDTO;
use HiEvents\Services\Application\Handlers\Contact\DTO\LookupContactByEmailPublicDTO;
use HiEvents\Services\Domain\Contact\ContactBackfillService;
use Illuminate\Support\Facades\DB;

readonly class LookupContactByEmailPublicHandler
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private ContactRepositoryInterface $contactRepository,
        private QuestionRepositoryInterface $questionRepository,
    ) {}

    public function handle(LookupContactByEmailPublicDTO $dto): ContactLookupResultDTO
    {
        $event = $this->eventRepository->findFirst($dto->eventId);
        if ($event === null) {
            return new ContactLookupResultDTO(found: false);
        }

        $contact = $this->contactRepository->findByEmailAndAccountId(
            email: $dto->email,
            accountId: $event->getAccountId(),
        );
        if ($contact === null) {
            return new ContactLookupResultDTO(found: false);
        }

        return new ContactLookupResultDTO(
            found: true,
            first_name: $contact->getFirstName(),
            last_name: $contact->getLastName(),
            question_answers: $this->buildQuestionAnswers(
                $event->getAccountId(),
                $dto->eventId,
                ContactBackfillService::normalizeAttributes($contact->getAttributes()),
            ),
        );
    }

    private function buildQuestionAnswers(int $accountId, int $eventId, array $attributes): array
    {
        if (empty($attributes)) {
            return [];
        }

        $definitionNamesById = DB::table('contact_attribute_definitions')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->pluck('name', 'id')
            ->toArray();

        if (empty($definitionNamesById)) {
            return [];
        }

        $questions = $this->questionRepository->findWhere([
            QuestionDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        $answers = [];
        /** @var QuestionDomainObject $question */
        foreach ($questions as $question) {
            $definitionId = $question->getContactAttributeDefinitionId();
            if ($definitionId === null) {
                continue;
            }
            $name = $definitionNamesById[$definitionId] ?? null;
            if ($name === null || !array_key_exists($name, $attributes)) {
                continue;
            }
            $answers[(string) $question->getId()] = $attributes[$name];
        }

        return $answers;
    }
}
