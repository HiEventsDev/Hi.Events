<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\ContactAttributeDefinitionDomainObject;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\Repository\Interfaces\ContactAttributeDefinitionRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

readonly class ReuseQuestionsHandler
{
    public function __construct(
        private QuestionRepositoryInterface $questionRepository,
        private ContactAttributeDefinitionRepositoryInterface $definitionRepository,
        private DatabaseManager $databaseManager,
    ) {}

    /**
     * @param  int[]  $questionIds
     * @return int Number of questions linked to a newly created attribute.
     */
    public function handle(int $accountId, array $questionIds): int
    {
        if (empty($questionIds)) {
            return 0;
        }

        $rows = DB::table('questions')
            ->join('events', 'events.id', '=', 'questions.event_id')
            ->whereIn('questions.id', $questionIds)
            ->whereNull('questions.contact_attribute_definition_id')
            ->whereNull('questions.deleted_at')
            ->where('events.account_id', $accountId)
            ->select('questions.id', 'questions.title', 'questions.type', 'questions.options')
            ->get();

        $linked = 0;

        foreach ($rows as $row) {
            try {
                $this->databaseManager->transaction(function () use ($accountId, $row) {
                    $definitionType = $this->deriveDefinitionType($row->type);
                    $options = $this->normalizeOptions($row->options, $definitionType);
                    $name = $this->uniqueSlugForAccount($accountId, (string) $row->title);

                    $definition = $this->definitionRepository->create([
                        ContactAttributeDefinitionDomainObject::ACCOUNT_ID => $accountId,
                        ContactAttributeDefinitionDomainObject::NAME => $name,
                        ContactAttributeDefinitionDomainObject::LABEL => (string) $row->title,
                        ContactAttributeDefinitionDomainObject::TYPE => $definitionType,
                        ContactAttributeDefinitionDomainObject::OPTIONS => $options,
                        ContactAttributeDefinitionDomainObject::SORT_ORDER => 0,
                        ContactAttributeDefinitionDomainObject::IS_ACTIVE => true,
                    ]);

                    $this->questionRepository->updateFromArray((int) $row->id, [
                        QuestionDomainObjectAbstract::CONTACT_ATTRIBUTE_DEFINITION_ID => $definition->getId(),
                    ]);
                });
                $linked++;
            } catch (\Throwable $e) {
                Log::error('Reuse questions: failed to create definition + link', [
                    'question_id' => $row->id,
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $linked;
    }

    private function deriveDefinitionType(?string $questionType): string
    {
        if ($questionType === QuestionTypeEnum::CHECKBOX->name || $questionType === QuestionTypeEnum::MULTI_SELECT_DROPDOWN->name) {
            return 'multi_select';
        }
        if ($questionType === QuestionTypeEnum::RADIO->name || $questionType === QuestionTypeEnum::DROPDOWN->name) {
            return 'select';
        }

        return 'text';
    }

    private function normalizeOptions(mixed $raw, string $definitionType): ?array
    {
        if ($definitionType === 'text') {
            return null;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($raw) || empty($raw)) {
            return null;
        }

        return array_values(array_map('strval', $raw));
    }

    private function uniqueSlugForAccount(int $accountId, string $title): string
    {
        $base = $this->slugify($title);
        $candidate = $base;
        $suffix = 2;
        while ($this->definitionRepository->findFirstWhere([
            [ContactAttributeDefinitionDomainObject::ACCOUNT_ID, '=', $accountId],
            [ContactAttributeDefinitionDomainObject::NAME, '=', $candidate],
        ]) !== null) {
            $candidate = $base.'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugify(string $input): string
    {
        $slug = strtolower(trim($input));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');

        return $slug !== '' ? $slug : 'attribute';
    }
}
