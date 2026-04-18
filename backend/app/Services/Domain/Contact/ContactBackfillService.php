<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Contact;

use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\ContactRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContactBackfillService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly ContactUpsertService $contactUpsertService,
    ) {}

    private function loadExistingContactAttributesByEmail(int $accountId): array
    {
        $rows = DB::table('contacts')
            ->select('email', 'attributes')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $attrs = self::normalizeAttributes($row->attributes);
            $out[strtolower($row->email)] = $attrs;
        }

        return $out;
    }

    /**
     * @return array<string, array<int, true>> Map of lowercase email → set of question_answer ids already evaluated.
     */
    private function loadProcessedQaIdsByContactEmail(int $accountId): array
    {
        $rows = DB::table('contacts')
            ->select('email', 'processed_question_answer_ids')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $ids = json_decode($row->processed_question_answer_ids ?? '[]', true) ?? [];
            $map[strtolower($row->email)] = array_flip(array_map('intval', $ids));
        }

        return $map;
    }

    private function walkLinkedAnswers(int $accountId, callable $consumer): void
    {
        DB::table('question_answers')
            ->join('questions', 'questions.id', '=', 'question_answers.question_id')
            ->join('orders', 'orders.id', '=', 'question_answers.order_id')
            ->join('events', 'events.id', '=', 'orders.event_id')
            ->leftJoin('attendees', 'attendees.id', '=', 'question_answers.attendee_id')
            ->leftJoin('contact_attribute_definitions', 'contact_attribute_definitions.id', '=', 'questions.contact_attribute_definition_id')
            ->whereNotNull('questions.contact_attribute_definition_id')
            ->whereNull('question_answers.deleted_at')
            ->where('events.account_id', $accountId)
            ->orderBy('question_answers.created_at')
            ->select([
                'question_answers.id as answer_id',
                'question_answers.order_id',
                'question_answers.attendee_id',
                'question_answers.answer',
                'question_answers.created_at as answer_created_at',
                'contact_attribute_definitions.name as definition_name',
                'contact_attribute_definitions.type as definition_type',
                'contact_attribute_definitions.options as definition_options',
                'attendees.email as attendee_email',
                'attendees.first_name as attendee_first_name',
                'attendees.last_name as attendee_last_name',
                'orders.email as buyer_email',
                'orders.first_name as buyer_first_name',
                'orders.last_name as buyer_last_name',
                'events.id as event_id',
                'events.title as event_title',
            ])
            ->cursor()
            ->each(fn ($row) => $consumer((array) $row));
    }

    /**
     * Phase A only, for a specific set of attendee ids. Each attendee is resolved to a contact
     * (find-or-create by email + account) and the attendee.contact_id is set.
     *
     * @param  int[]  $attendeeIds
     * @return int Number of attendees linked.
     */
    public function linkAttendeesById(int $accountId, array $attendeeIds): int
    {
        if (empty($attendeeIds)) {
            return 0;
        }

        $linked = 0;

        $rows = DB::table('attendees')
            ->join('events', 'events.id', '=', 'attendees.event_id')
            ->whereIn('attendees.id', $attendeeIds)
            ->whereNull('attendees.contact_id')
            ->whereNull('attendees.deleted_at')
            ->whereNull('attendees.contact_link_ignored_at')
            ->where('events.account_id', $accountId)
            ->select('attendees.id', 'attendees.email', 'attendees.first_name', 'attendees.last_name')
            ->get();

        foreach ($rows as $row) {
            try {
                $contact = $this->contactUpsertService->findOrCreateContact(
                    accountId: $accountId,
                    email: $row->email,
                    firstName: $row->first_name,
                    lastName: $row->last_name,
                );
                $this->attendeeRepository->updateFromArray((int) $row->id, [
                    AttendeeDomainObjectAbstract::CONTACT_ID => $contact->getId(),
                ]);
                $linked++;
            } catch (\Throwable $e) {
                Log::error('Contact backfill: failed to link attendee (bulk add)', [
                    'attendee_id' => $row->id,
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $linked;
    }

    /**
     * Apply a set of per-conflict decisions. Each decision is either 'update' (overwrite the
     * contact attribute with the event answer) or 'leave_alone' (keep existing; only mark processed).
     * Both outcomes append the QA id to contacts.processed_question_answer_ids so the conflict
     * does not reappear on subsequent previews.
     *
     * @param  array<array{question_answer_id:int,decision:string}>  $decisions
     * @return int Number of decisions processed.
     */
    public function applyConflictDecisions(int $accountId, array $decisions, int $changedByUserId): int
    {
        if (empty($decisions)) {
            return 0;
        }

        $decisionMap = [];
        foreach ($decisions as $d) {
            $decisionMap[(int) $d['question_answer_id']] = $d['decision'];
        }
        $qaIds = array_keys($decisionMap);

        $rows = DB::table('question_answers')
            ->join('questions', 'questions.id', '=', 'question_answers.question_id')
            ->join('orders', 'orders.id', '=', 'question_answers.order_id')
            ->join('events', 'events.id', '=', 'orders.event_id')
            ->leftJoin('attendees', 'attendees.id', '=', 'question_answers.attendee_id')
            ->leftJoin('contact_attribute_definitions', 'contact_attribute_definitions.id', '=', 'questions.contact_attribute_definition_id')
            ->whereIn('question_answers.id', $qaIds)
            ->whereNotNull('questions.contact_attribute_definition_id')
            ->whereNull('question_answers.deleted_at')
            ->where('events.account_id', $accountId)
            ->select([
                'question_answers.id as answer_id',
                'question_answers.order_id',
                'question_answers.attendee_id',
                'question_answers.answer',
                'contact_attribute_definitions.name as definition_name',
                'attendees.email as attendee_email',
                'attendees.first_name as attendee_first_name',
                'attendees.last_name as attendee_last_name',
                'orders.email as buyer_email',
                'orders.first_name as buyer_first_name',
                'orders.last_name as buyer_last_name',
            ])
            ->get();

        $pendingByContactId = [];
        $qaIdsByContactId = [];
        $processed = 0;

        foreach ($rows as $row) {
            $rowArr = (array) $row;
            $contactEmail = self::resolveContactEmail($rowArr);
            if ($contactEmail === null) {
                continue;
            }
            $decision = $decisionMap[(int) $rowArr['answer_id']] ?? 'leave_alone';

            $contact = $this->contactUpsertService->findOrCreateContact(
                accountId: $accountId,
                email: $contactEmail,
                firstName: $rowArr['buyer_first_name'] ?? null,
                lastName: $rowArr['buyer_last_name'] ?? null,
            );

            $contactId = $contact->getId();
            $qaIdsByContactId[$contactId][] = (int) $rowArr['answer_id'];

            if ($decision === 'update') {
                $proposed = self::decodeAnswer($rowArr['answer']);
                $pendingByContactId[$contactId][$rowArr['definition_name']] = $proposed;
            }

            $processed++;
        }

        $contactIds = array_unique(array_merge(array_keys($pendingByContactId), array_keys($qaIdsByContactId)));
        foreach ($contactIds as $contactId) {
            $attributes = $pendingByContactId[$contactId] ?? [];
            $qaIds = $qaIdsByContactId[$contactId] ?? [];
            if (empty($attributes) && empty($qaIds)) {
                continue;
            }
            $contact = $this->contactRepository->findById($contactId);
            $this->contactUpsertService->updateContactAttributes(
                contact: $contact,
                newAttributes: $attributes,
                changedByUserId: $changedByUserId,
                sourceQuestionAnswerIds: $qaIds,
            );
        }

        return $processed;
    }

    public function applyOrderAnswers(int $orderId, int $accountId, int $changedByUserId): int
    {
        $rows = DB::table('question_answers')
            ->join('questions', 'questions.id', '=', 'question_answers.question_id')
            ->join('orders', 'orders.id', '=', 'question_answers.order_id')
            ->leftJoin('attendees', 'attendees.id', '=', 'question_answers.attendee_id')
            ->leftJoin('contact_attribute_definitions', 'contact_attribute_definitions.id', '=', 'questions.contact_attribute_definition_id')
            ->where('question_answers.order_id', $orderId)
            ->whereNotNull('questions.contact_attribute_definition_id')
            ->whereNull('question_answers.deleted_at')
            ->select([
                'question_answers.id as answer_id',
                'question_answers.order_id',
                'question_answers.attendee_id',
                'question_answers.answer',
                'contact_attribute_definitions.name as definition_name',
                'attendees.email as attendee_email',
                'attendees.first_name as attendee_first_name',
                'attendees.last_name as attendee_last_name',
                'orders.email as buyer_email',
                'orders.first_name as buyer_first_name',
                'orders.last_name as buyer_last_name',
            ])
            ->get();

        $pendingByContactId = [];
        $qaIdsByContactId = [];
        $writtenCount = 0;

        foreach ($rows as $row) {
            $rowArr = (array) $row;
            $contactEmail = self::resolveContactEmail($rowArr);
            if ($contactEmail === null) {
                continue;
            }

            $contact = $this->contactUpsertService->findOrCreateContact(
                accountId: $accountId,
                email: $contactEmail,
                firstName: $rowArr['buyer_first_name'] ?? null,
                lastName: $rowArr['buyer_last_name'] ?? null,
            );

            $contactId = $contact->getId();
            $attributeName = $rowArr['definition_name'];
            $proposed = self::decodeAnswer($rowArr['answer']);
            $currentAttributes = self::normalizeAttributes($contact->getAttributes());
            $pending = $pendingByContactId[$contactId] ?? [];
            $effective = array_merge($currentAttributes, $pending);
            $existing = $effective[$attributeName] ?? null;

            if ($existing === null) {
                $pending[$attributeName] = $proposed;
                $qaIdsByContactId[$contactId][] = (int) $rowArr['answer_id'];
                $writtenCount++;
            } elseif ($existing === $proposed) {
                $qaIdsByContactId[$contactId][] = (int) $rowArr['answer_id'];
            }

            $pendingByContactId[$contactId] = $pending;
        }

        $contactIds = array_unique(array_merge(array_keys($pendingByContactId), array_keys($qaIdsByContactId)));
        foreach ($contactIds as $contactId) {
            $attributes = $pendingByContactId[$contactId] ?? [];
            $qaIds = $qaIdsByContactId[$contactId] ?? [];
            if (empty($attributes) && empty($qaIds)) {
                continue;
            }
            $contact = $this->contactRepository->findById($contactId);
            $this->contactUpsertService->updateContactAttributes(
                contact: $contact,
                newAttributes: $attributes,
                changedByUserId: $changedByUserId,
                sourceQuestionAnswerIds: $qaIds,
            );
        }

        return $writtenCount;
    }

    public static function resolveContactEmail(array $row): ?string
    {
        if (($row['attendee_id'] ?? null) !== null) {
            return $row['attendee_email'] ?? null;
        }

        return $row['buyer_email'] ?? null;
    }

    public static function normalizeAttributes(mixed $attributes): array
    {
        if (is_array($attributes)) {
            return $attributes;
        }
        if (is_string($attributes)) {
            return json_decode($attributes, true) ?? [];
        }

        return [];
    }

    public static function decodeAnswer(mixed $answer): mixed
    {
        if (! is_string($answer)) {
            return $answer;
        }

        $trimmed = trim($answer);
        if ($trimmed === '') {
            return $answer;
        }

        $first = $trimmed[0];
        if ($first !== '[' && $first !== '"' && $first !== '{') {
            return $answer;
        }

        $decoded = json_decode($answer, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $answer;
    }

    public function getSummaryCounts(int $accountId): array
    {
        $unlinkedAttendees = DB::table('attendees')
            ->join('events', 'events.id', '=', 'attendees.event_id')
            ->whereNull('attendees.contact_id')
            ->whereNull('attendees.deleted_at')
            ->whereNull('attendees.contact_link_ignored_at')
            ->where('events.account_id', $accountId)
            ->count();

        $unmappedQuestions = DB::table('questions')
            ->join('events', 'events.id', '=', 'questions.event_id')
            ->join('question_answers', 'question_answers.question_id', '=', 'questions.id')
            ->whereNull('questions.contact_attribute_definition_id')
            ->whereNull('questions.deleted_at')
            ->whereNull('questions.contact_link_ignored_at')
            ->whereNull('question_answers.deleted_at')
            ->where('events.account_id', $accountId)
            ->distinct('questions.id')
            ->count('questions.id');

        $conflictsTotal = $this->getConflicts(
            $accountId,
            QueryParamsDTO::fromArray(['per_page' => 1, 'page' => 1]),
            includeProcessed: false,
        )->total();

        return [
            'unlinked_attendees_count' => (int) $unlinkedAttendees,
            'unmapped_questions_count' => (int) $unmappedQuestions,
            'conflicts_count' => (int) $conflictsTotal,
        ];
    }

    public function getUnlinkedAttendees(int $accountId, QueryParamsDTO $params, bool $includeIgnored = false): LengthAwarePaginator
    {
        $query = DB::table('attendees')
            ->join('events', 'events.id', '=', 'attendees.event_id')
            ->whereNull('attendees.contact_id')
            ->whereNull('attendees.deleted_at')
            ->where('events.account_id', $accountId)
            ->select(
                'attendees.id',
                'attendees.email',
                'attendees.first_name',
                'attendees.last_name',
                'attendees.event_id',
                'events.title as event_title',
                'attendees.created_at',
                'attendees.contact_link_ignored_at',
            );

        if (! $includeIgnored) {
            $query->whereNull('attendees.contact_link_ignored_at');
        }

        if ($params->query !== null && $params->query !== '') {
            $needle = '%'.$params->query.'%';
            $query->where(function ($q) use ($needle) {
                $q->where('attendees.email', 'ilike', $needle)
                    ->orWhere('attendees.first_name', 'ilike', $needle)
                    ->orWhere('attendees.last_name', 'ilike', $needle);
            });
        }

        foreach ($params->filter_fields ?? [] as $filter) {
            if ($filter->field === 'event_id' && $filter->value) {
                $query->where('attendees.event_id', $filter->value);
            }
        }

        $sortableColumns = [
            'email' => 'attendees.email',
            'first_name' => 'attendees.first_name',
            'last_name' => 'attendees.last_name',
            'event_title' => 'events.title',
            'created_at' => 'attendees.created_at',
        ];
        $sortColumn = $sortableColumns[$params->sort_by] ?? 'attendees.id';
        $sortDirection = strtolower($params->sort_direction ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortColumn, $sortDirection);

        return $query->paginate($params->per_page ?: 25, ['*'], 'page', $params->page ?: 1);
    }

    public function getUnmappedQuestions(int $accountId, QueryParamsDTO $params, bool $includeIgnored = false): LengthAwarePaginator
    {
        $query = DB::table('questions')
            ->join('events', 'events.id', '=', 'questions.event_id')
            ->join('question_answers', 'question_answers.question_id', '=', 'questions.id')
            ->whereNull('questions.contact_attribute_definition_id')
            ->whereNull('questions.deleted_at')
            ->whereNull('question_answers.deleted_at')
            ->where('events.account_id', $accountId)
            ->groupBy('questions.id', 'questions.title', 'questions.event_id', 'events.title', 'questions.contact_link_ignored_at')
            ->select(
                'questions.id as question_id',
                'questions.title',
                'questions.event_id',
                'events.title as event_title',
                'questions.contact_link_ignored_at',
                DB::raw('COUNT(question_answers.id) as answer_count'),
            );

        if (! $includeIgnored) {
            $query->whereNull('questions.contact_link_ignored_at');
        }

        if ($params->query !== null && $params->query !== '') {
            $query->where('questions.title', 'ilike', '%'.$params->query.'%');
        }

        foreach ($params->filter_fields ?? [] as $filter) {
            if ($filter->field === 'event_id' && $filter->value) {
                $query->where('questions.event_id', $filter->value);
            }
        }

        $sortableColumns = [
            'title' => 'questions.title',
            'event_title' => 'events.title',
        ];
        $sortColumn = $sortableColumns[$params->sort_by] ?? 'questions.id';
        $sortDirection = strtolower($params->sort_direction ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortColumn, $sortDirection);

        return $query->paginate($params->per_page ?: 25, ['*'], 'page', $params->page ?: 1);
    }

    public function getConflicts(
        int $accountId,
        QueryParamsDTO $params,
        bool $includeProcessed = false,
    ): LengthAwarePaginator {
        $emailToContactAttributes = $this->loadExistingContactAttributesByEmail($accountId);
        $processedByEmail = $this->loadProcessedQaIdsByContactEmail($accountId);
        $conflicts = [];

        $eventFilterId = null;
        foreach ($params->filter_fields ?? [] as $filter) {
            if ($filter->field === 'event_id' && $filter->value) {
                $eventFilterId = (int) $filter->value;
            }
        }

        $this->walkLinkedAnswers($accountId, function (array $row) use (
            &$conflicts,
            &$emailToContactAttributes,
            &$processedByEmail,
            $includeProcessed,
            $eventFilterId,
        ) {
            $contactEmail = self::resolveContactEmail($row);
            if ($contactEmail === null) {
                return;
            }

            $emailKey = strtolower($contactEmail);
            $answerId = (int) $row['answer_id'];
            $isProcessed = isset($processedByEmail[$emailKey][$answerId]);

            if ($isProcessed && ! $includeProcessed) {
                return;
            }

            if (! isset($emailToContactAttributes[$emailKey])) {
                return;
            }

            $attributeName = $row['definition_name'];
            $proposed = self::decodeAnswer($row['answer']);
            $current = $emailToContactAttributes[$emailKey][$attributeName] ?? null;

            if ($current === $proposed) {
                return;
            }

            if ($eventFilterId !== null && (int) ($row['event_id'] ?? 0) !== $eventFilterId) {
                return;
            }

            $conflicts[] = [
                'question_answer_id' => $answerId,
                'contact_email' => $contactEmail,
                'attribute_name' => $attributeName,
                'existing_value' => $current,
                'proposed_value' => $proposed,
                'source_order_id' => (int) $row['order_id'],
                'source_attendee_id' => $row['attendee_id'] !== null ? (int) $row['attendee_id'] : null,
                'answered_at' => $row['answer_created_at'] ?? null,
                'event_id' => (int) ($row['event_id'] ?? 0),
                'event_title' => $row['event_title'] ?? null,
                'processed' => $isProcessed,
            ];
        });

        if ($params->query !== null && $params->query !== '') {
            $needle = strtolower($params->query);
            $conflicts = array_values(array_filter($conflicts, fn ($c) => str_contains(strtolower($c['contact_email']), $needle)
                || str_contains(strtolower((string) $c['attribute_name']), $needle)
            ));
        }

        $sortBy = $params->sort_by ?? 'contact_email';
        $sortDir = strtolower($params->sort_direction ?? 'asc') === 'desc' ? 'desc' : 'asc';
        usort($conflicts, function ($a, $b) use ($sortBy, $sortDir) {
            $av = (string) ($a[$sortBy] ?? '');
            $bv = (string) ($b[$sortBy] ?? '');
            $cmp = strcasecmp($av, $bv);

            return $sortDir === 'asc' ? $cmp : -$cmp;
        });

        $perPage = $params->per_page ?: 25;
        $page = $params->page ?: 1;
        $total = count($conflicts);
        $items = array_slice($conflicts, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => request()->url(), 'query' => request()->query()],
        );
    }
}
