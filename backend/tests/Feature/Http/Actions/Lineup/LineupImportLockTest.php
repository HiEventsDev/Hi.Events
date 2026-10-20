<?php

namespace Tests\Feature\Http\Actions\Lineup;

use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class LineupImportLockTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_import_takes_a_lineup_lock_instead_of_locking_the_event_row_checkout_needs(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $eventId = $this->createEvent($accountId, $organizerId);
        $headers = $this->headersFor($owner, $accountId);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->postJson("/events/$eventId/lineup/import", [
            'people' => [['name' => 'Nova Lights']],
            'slots' => [[
                'starts_at' => '2030-06-14 19:00:00',
                'ends_at' => '2030-06-14 20:00:00',
                'people' => ['Nova Lights'],
            ]],
        ], $headers)->assertOk()->assertJson(['people_created' => 1, 'slots_created' => 1]);

        $eventRowLocks = array_filter(
            $queries,
            static fn (string $sql) => preg_match('/from "events".*\bfor (update|no key update|share)\b/is', $sql) === 1,
        );
        $this->assertSame([], array_values($eventRowLocks));

        $this->assertSame(1, $this->heldAdvisoryLocks(TransactionLockService::LINEUP_LOCK_KEYSPACE, $eventId));
        $this->assertSame(0, $this->heldAdvisoryLocks(TransactionLockService::EVENT_LOCK_KEYSPACE, $eventId));
    }

    private function heldAdvisoryLocks(int $keyspace, int $key): int
    {
        return (int) DB::selectOne(
            "SELECT count(*) AS held FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid() AND granted AND classid::bigint = ? AND objid::bigint = ? AND objsubid = 2",
            [$keyspace, $key],
        )->held;
    }
}
