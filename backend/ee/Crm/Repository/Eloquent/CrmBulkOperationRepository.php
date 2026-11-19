<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmBulkOperationDomainObject;
use HiEvents\DomainObjects\Enums\CrmBulkAction;
use HiEvents\DomainObjects\Status\CrmBulkOperationStatus;
use HiEvents\Enterprise\Crm\Repository\Eloquent\Concerns\LocksCrmOrganizer;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmBulkOperationRepositoryInterface;
use HiEvents\Models\CrmBulkOperation;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<CrmBulkOperationDomainObject>
 */
class CrmBulkOperationRepository extends BaseRepository implements CrmBulkOperationRepositoryInterface
{
    use LocksCrmOrganizer;

    private const STALLED_AFTER_MINUTES = 30;

    protected function getModel(): string
    {
        return CrmBulkOperation::class;
    }

    public function getDomainObject(): string
    {
        return CrmBulkOperationDomainObject::class;
    }

    public function failStalled(?int $organizerId = null): void
    {
        $this->runQuery(fn () => $this->db->table('crm_bulk_operations')
            ->when($organizerId !== null, static fn ($operations) => $operations->where('organizer_id', $organizerId))
            ->where('status', CrmBulkOperationStatus::RUNNING->value)
            ->where('updated_at', '<', now()->subMinutes(self::STALLED_AFTER_MINUTES))
            ->update(['status' => CrmBulkOperationStatus::FAILED->value, 'finished_at' => now(), 'updated_at' => now()]));
    }

    public function hasRunningDestructiveOperation(int $organizerId): bool
    {
        $this->failStalled($organizerId);

        return $this->runQuery(fn () => $this->db->table('crm_bulk_operations')
            ->where('organizer_id', $organizerId)
            ->where('status', CrmBulkOperationStatus::RUNNING->value)
            ->whereIn('action', [CrmBulkAction::UNSUBSCRIBE->value, CrmBulkAction::DELETE->value])
            ->exists());
    }

    public function claimNext(int $operationId, int $afterContactId, int $limit): array
    {
        return $this->runQuery(fn () => array_map(static fn (object $row) => (int) $row->contact_id, $this->db->select('
            UPDATE crm_bulk_operation_contacts
            SET processed = true
            WHERE operation_id = ? AND contact_id IN (
                SELECT contact_id
                FROM crm_bulk_operation_contacts
                WHERE operation_id = ? AND processed = false AND contact_id > ?
                ORDER BY contact_id
                LIMIT ?
            )
            RETURNING contact_id
        ', [$operationId, $operationId, $afterContactId, $limit])));
    }

    public function recordProgress(int $operationId, int $processed, array $changedContactIds): void
    {
        $this->runQuery(function () use ($operationId, $processed, $changedContactIds): void {
            if ($changedContactIds !== []) {
                $this->db->table('crm_bulk_operation_contacts')
                    ->where('operation_id', $operationId)
                    ->whereIn('contact_id', $changedContactIds)
                    ->update(['changed' => true]);
            }

            $this->db->table('crm_bulk_operations')->where('id', $operationId)->update([
                'processed' => $this->db->raw('processed + '.$processed),
                'changed' => $this->db->raw('changed + '.count($changedContactIds)),
                'updated_at' => now(),
            ]);
        });
    }

    public function completeIfFinished(int $operationId): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_bulk_operations')
            ->where('id', $operationId)
            ->where('status', CrmBulkOperationStatus::RUNNING->value)
            ->whereColumn('processed', '>=', 'total')
            ->update(['status' => CrmBulkOperationStatus::COMPLETED->value, 'finished_at' => now(), 'updated_at' => now()]) > 0);
    }

    public function markFailed(int $operationId): void
    {
        $this->runQuery(fn () => $this->db->table('crm_bulk_operations')
            ->where('id', $operationId)
            ->where('status', CrmBulkOperationStatus::RUNNING->value)
            ->update(['status' => CrmBulkOperationStatus::FAILED->value, 'finished_at' => now(), 'updated_at' => now()]));
    }

    public function claimNextChanged(int $operationId, int $limit): array
    {
        return $this->runQuery(fn () => array_map(static fn (object $row) => (int) $row->contact_id, $this->db->select('
            UPDATE crm_bulk_operation_contacts
            SET changed = false
            WHERE operation_id = ? AND contact_id IN (
                SELECT contact_id
                FROM crm_bulk_operation_contacts
                WHERE operation_id = ? AND changed = true
                ORDER BY contact_id
                LIMIT ?
            )
            RETURNING contact_id
        ', [$operationId, $operationId, $limit])));
    }

    public function addTags(int $organizerId, array $contactIds, array $tagIds): array
    {
        if ($contactIds === [] || $tagIds === []) {
            return [];
        }

        return $this->runQuery(fn () => $this->distinctContactIds($this->db->select('
            INSERT INTO crm_contact_tags (contact_id, tag_id, created_at)
            SELECT c.id, t.id, now()
            FROM crm_contacts c
            JOIN crm_tags t ON t.organizer_id = c.organizer_id AND t.id = ANY(CAST(? AS bigint[]))
            WHERE c.organizer_id = ? AND c.id = ANY(CAST(? AS bigint[]))
            ON CONFLICT (contact_id, tag_id) DO NOTHING
            RETURNING contact_id
        ', [$this->idArray($tagIds), $organizerId, $this->idArray($contactIds)])));
    }

    public function removeTags(int $organizerId, array $contactIds, array $tagIds): array
    {
        if ($contactIds === [] || $tagIds === []) {
            return [];
        }

        return $this->runQuery(fn () => $this->distinctContactIds($this->db->select('
            DELETE FROM crm_contact_tags ct
            USING crm_contacts c
            WHERE c.id = ct.contact_id
              AND c.organizer_id = ?
              AND ct.contact_id = ANY(CAST(? AS bigint[]))
              AND ct.tag_id = ANY(CAST(? AS bigint[]))
            RETURNING ct.contact_id
        ', [$organizerId, $this->idArray($contactIds), $this->idArray($tagIds)])));
    }

    public function pruneSnapshotsFinishedBefore(CarbonInterface $before, int $limit): int
    {
        return $this->runQuery(fn () => $this->db->delete('
            DELETE FROM crm_bulk_operation_contacts
            WHERE (operation_id, contact_id) IN (
                SELECT c.operation_id, c.contact_id
                FROM crm_bulk_operation_contacts c
                JOIN crm_bulk_operations o ON o.id = c.operation_id
                WHERE o.finished_at < ?
                LIMIT ?
            )
        ', [$before->toDateTimeString(), $limit]));
    }

    /**
     * @param  int[]  $ids
     */
    private function idArray(array $ids): string
    {
        return '{'.implode(',', array_map('intval', $ids)).'}';
    }

    /**
     * @param  object[]  $rows
     * @return int[]
     */
    private function distinctContactIds(array $rows): array
    {
        return array_values(array_unique(array_map(static fn (object $row) => (int) $row->contact_id, $rows)));
    }
}
