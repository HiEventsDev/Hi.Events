<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\ContactAttributeDefinitions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\ContactAttributeDefinitionDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\ContactAttributeDefinitionRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GetLinkedQuestionsAction extends BaseAction
{
    public function __construct(
        private readonly ContactAttributeDefinitionRepositoryInterface $repository,
    ) {}

    public function __invoke(Request $request, int $accountId, int $definitionId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $authAccountId = $this->getAuthenticatedAccountId();

        $this->repository->findFirstWhere([
            ContactAttributeDefinitionDomainObject::ID => $definitionId,
            ContactAttributeDefinitionDomainObject::ACCOUNT_ID => $authAccountId,
        ]);

        $params = QueryParamsDTO::fromArray($request->query->all());
        $page = max(1, (int) ($params->page ?? 1));
        $perPage = min(100, max(1, (int) ($params->per_page ?? 25)));

        $base = DB::table('questions')
            ->join('events', 'events.id', '=', 'questions.event_id')
            ->where('questions.contact_attribute_definition_id', $definitionId)
            ->where('events.account_id', $authAccountId)
            ->whereNull('questions.deleted_at')
            ->whereNull('events.deleted_at');

        if (!empty($params->query)) {
            $needle = '%' . $params->query . '%';
            $base->where(function ($q) use ($needle) {
                $q->where('questions.title', 'ILIKE', $needle)
                    ->orWhere('events.title', 'ILIKE', $needle);
            });
        }

        $total = (clone $base)->count();

        $rows = $base
            ->orderBy('events.start_date', 'desc')
            ->orderBy('questions.id', 'desc')
            ->forPage($page, $perPage)
            ->get([
                'questions.id as question_id',
                'questions.title as question_title',
                'questions.belongs_to as question_belongs_to',
                'questions.required as question_required',
                'questions.is_hidden as question_is_hidden',
                'questions.type as question_type',
                'events.id as event_id',
                'events.title as event_title',
                'events.start_date as event_start_date',
                'events.status as event_status',
            ])
            ->map(fn ($row) => [
                'question_id' => (int) $row->question_id,
                'question_title' => $row->question_title,
                'question_belongs_to' => $row->question_belongs_to,
                'question_required' => (bool) $row->question_required,
                'question_is_hidden' => (bool) $row->question_is_hidden,
                'question_type' => $row->question_type,
                'event_id' => (int) $row->event_id,
                'event_title' => $row->event_title,
                'event_start_date' => $row->event_start_date,
                'event_status' => $row->event_status,
            ])
            ->all();

        $paginator = new LengthAwarePaginator($rows, $total, $perPage, $page);
        $array = $paginator->toArray();

        return $this->jsonResponse([
            'data' => $array['data'],
            'meta' => [
                'current_page' => $array['current_page'],
                'per_page' => $array['per_page'],
                'total' => $array['total'],
                'last_page' => $array['last_page'],
                'from' => $array['from'],
                'to' => $array['to'],
            ],
        ]);
    }
}
