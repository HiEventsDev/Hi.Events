<?php

namespace HiEvents\Http\Actions\ContactAttributeDefinitions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\ContactAttributeDefinitionDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\ContactAttributeDefinitionRepositoryInterface;
use HiEvents\Resources\Contact\ContactAttributeDefinitionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GetContactAttributeDefinitionsAction extends BaseAction
{
    public function __construct(
        private readonly ContactAttributeDefinitionRepositoryInterface $repository,
    ) {
    }

    public function __invoke(int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $definitions = $this->repository->findWhere([
            ContactAttributeDefinitionDomainObject::ACCOUNT_ID => $this->getAuthenticatedAccountId(),
        ]);

        $ids = $definitions->map(fn (ContactAttributeDefinitionDomainObject $d) => $d->getId())->all();
        $counts = $this->loadLinkedCounts($ids);

        $definitions->each(function (ContactAttributeDefinitionDomainObject $def) use ($counts) {
            $def->setLinkedQuestionCount($counts['questions'][$def->getId()] ?? 0);
            $def->setLinkedEventCount($counts['events'][$def->getId()] ?? 0);
        });

        return $this->resourceResponse(ContactAttributeDefinitionResource::class, $definitions);
    }

    /**
     * @param  array<int>  $definitionIds
     * @return array{questions: array<int,int>, events: array<int,int>}
     */
    private function loadLinkedCounts(array $definitionIds): array
    {
        if ($definitionIds === []) {
            return ['questions' => [], 'events' => []];
        }

        $rows = DB::table('questions')
            ->whereIn('contact_attribute_definition_id', $definitionIds)
            ->whereNull('deleted_at')
            ->groupBy('contact_attribute_definition_id')
            ->select(
                'contact_attribute_definition_id as definition_id',
                DB::raw('count(*) as question_count'),
                DB::raw('count(distinct event_id) as event_count'),
            )
            ->get();

        $questions = [];
        $events = [];
        foreach ($rows as $row) {
            $questions[(int) $row->definition_id] = (int) $row->question_count;
            $events[(int) $row->definition_id] = (int) $row->event_count;
        }

        return ['questions' => $questions, 'events' => $events];
    }
}
