<?php

namespace HiEvents\Services\Domain\Account\Anonymization;

use HiEvents\DomainObjects\Enums\AnonymizationStrategy;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AnonymizationExecutor
{
    public function scrub(
        Builder $query,
        string $entity,
        array $columnStrategies,
        AnonymizationContext $context,
    ): EntityAnonymizationResult {
        $updates = [];

        foreach ($columnStrategies as $column => $strategy) {
            $updates[$column] = $this->valueForStrategy($strategy, $context);
        }

        return new EntityAnonymizationResult(
            entity: $entity,
            action: 'scrubbed',
            rowCount: $query->update($updates),
            columns: array_keys($columnStrategies),
        );
    }

    public function delete(Builder $query, string $entity): EntityAnonymizationResult
    {
        return new EntityAnonymizationResult(
            entity: $entity,
            action: 'deleted',
            rowCount: $query->delete(),
        );
    }

    public function softDelete(Builder $query, string $entity): EntityAnonymizationResult
    {
        return new EntityAnonymizationResult(
            entity: $entity,
            action: 'soft_deleted',
            rowCount: $query->whereNull('deleted_at')->update(['deleted_at' => now()]),
        );
    }

    private function valueForStrategy(AnonymizationStrategy $strategy, AnonymizationContext $context): mixed
    {
        return match ($strategy) {
            AnonymizationStrategy::NULLIFY => null,
            AnonymizationStrategy::SCRUB_TEXT => 'Anonymized',
            AnonymizationStrategy::SCRUB_EMAIL => sprintf('anonymized+account-%d@anonymized.invalid', $context->accountId),
            AnonymizationStrategy::SCRUB_EMAIL_UNIQUE => DB::raw("'anonymized+' || id || '@anonymized.invalid'"),
            AnonymizationStrategy::RANDOM_TOKEN => DB::raw('md5(random()::text || clock_timestamp()::text || id::text)'),
        };
    }
}
