<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use HiEvents\DomainObjects\CrmImportDomainObject;
use HiEvents\DomainObjects\Generated\CrmImportDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\DomainObjects\Status\CrmImportStatus;
use HiEvents\Enterprise\Crm\Repository\DTO\ImportChunkResultDTO;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmImportRepositoryInterface;
use HiEvents\Models\CrmImport;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CrmImportDomainObject>
 */
class CrmImportRepository extends BaseRepository implements CrmImportRepositoryInterface
{
    private const STALLED_AFTER_MINUTES = 30;

    private const ROWS_PER_INSERT = 1000;

    protected function getModel(): string
    {
        return CrmImport::class;
    }

    public function getDomainObject(): string
    {
        return CrmImportDomainObject::class;
    }

    public function findRecentForOrganizer(int $organizerId, int $limit): Collection
    {
        return $this->runQuery(function () use ($organizerId, $limit): Collection {
            $imports = $this->handleResults($this->model
                ->where(CrmImportDomainObjectAbstract::ORGANIZER_ID, $organizerId)
                ->orderByDesc(CrmImportDomainObjectAbstract::CREATED_AT)
                ->orderByDesc(CrmImportDomainObjectAbstract::ID)
                ->limit($limit)
                ->get());

            $users = $this->db->table('users')
                ->whereIn('id', $imports->map(static fn (CrmImportDomainObject $import) => $import->getUserId())->filter()->unique()->all())
                ->get(['id', 'first_name', 'last_name'])
                ->keyBy('id');

            return $imports->each(static function (CrmImportDomainObject $import) use ($users): void {
                $user = $users[$import->getUserId()] ?? null;
                $name = $user === null ? '' : trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                $import->setUserName($name === '' ? null : $name);
            });
        });
    }

    public function addRows(int $importId, array $rows): void
    {
        $this->runQuery(function () use ($importId, $rows): void {
            foreach (array_chunk($rows, self::ROWS_PER_INSERT) as $chunk) {
                $this->db->table('crm_import_rows')->insert(array_map(static fn (array $row) => [
                    'import_id' => $importId,
                    'email' => $row['email'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'reason' => $row['reason']->value,
                ], $chunk));
            }
        });
    }

    public function lockIfProcessing(int $importId): ?CrmImportDomainObject
    {
        return $this->runQuery(fn () => $this->handleSingleResult($this->model->newQuery()
            ->where(CrmImportDomainObjectAbstract::ID, $importId)
            ->where(CrmImportDomainObjectAbstract::STATUS, CrmImportStatus::PROCESSING->value)
            ->lockForUpdate()
            ->first()));
    }

    public function claimRows(int $importId, int $limit): array
    {
        return $this->runQuery(fn () => array_map(static fn (object $row) => [
            'email' => $row->email,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'reason' => CrmEmailMarketingStatus::from($row->reason),
        ], $this->db->select('
            DELETE FROM crm_import_rows
            WHERE id IN (
                SELECT id FROM crm_import_rows WHERE import_id = ? ORDER BY id LIMIT ?
            )
            RETURNING email, first_name, last_name, reason
        ', [$importId, $limit])));
    }

    public function recordProgress(int $importId, ImportChunkResultDTO $result): void
    {
        $this->runQuery(fn () => $this->db->update("
            UPDATE crm_imports SET
                created_count = created_count + ?,
                updated_count = updated_count + ?,
                skipped_count = skipped_count + ?,
                skipped_reasons = skipped_reasons || jsonb_build_object(
                    'previously_deleted', CAST(skipped_reasons->>'previously_deleted' AS integer) + ?,
                    'already_suppressed', CAST(skipped_reasons->>'already_suppressed' AS integer) + ?,
                    'bounced_secondary_address', CAST(skipped_reasons->>'bounced_secondary_address' AS integer) + ?
                ),
                updated_at = ?
            WHERE id = ?
        ", [
            $result->created,
            $result->updated,
            $result->previously_deleted + $result->already_suppressed + $result->bounced_secondary_address,
            $result->previously_deleted,
            $result->already_suppressed,
            $result->bounced_secondary_address,
            now()->toDateTimeString(),
            $importId,
        ]));
    }

    public function completeIfFinished(int $importId): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_imports')
            ->where('id', $importId)
            ->where('status', CrmImportStatus::PROCESSING->value)
            ->whereNotExists(static fn ($rows) => $rows
                ->selectRaw('1')
                ->from('crm_import_rows')
                ->whereColumn('crm_import_rows.import_id', 'crm_imports.id'))
            ->update(['status' => CrmImportStatus::COMPLETED->value, 'updated_at' => now()]) > 0);
    }

    public function markFailed(int $importId): void
    {
        $this->runQuery(fn () => $this->fail($this->db->table('crm_imports')->where('id', $importId)));
    }

    public function failStalled(?int $organizerId = null): void
    {
        $this->runQuery(fn () => $this->fail($this->db->table('crm_imports')
            ->when($organizerId !== null, static fn ($imports) => $imports->where('organizer_id', $organizerId))
            ->where('updated_at', '<', now()->subMinutes(self::STALLED_AFTER_MINUTES))));
    }

    private function fail(QueryBuilder $imports): void
    {
        $failedIds = array_map(static fn (object $row) => (int) $row->id, $this->db->select(
            'UPDATE crm_imports SET status = ?, updated_at = ? WHERE status = ? AND id IN ('.$imports->select('id')->toSql().') RETURNING id',
            [CrmImportStatus::FAILED->value, now()->toDateTimeString(), CrmImportStatus::PROCESSING->value, ...$imports->getBindings()],
        ));

        $this->db->table('crm_import_rows')->whereIn('import_id', $failedIds)->delete();
    }
}
