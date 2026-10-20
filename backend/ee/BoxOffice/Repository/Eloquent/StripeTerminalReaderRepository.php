<?php

namespace HiEvents\Enterprise\BoxOffice\Repository\Eloquent;

use HiEvents\DomainObjects\StripeTerminalReaderDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\StripeTerminalReaderRepositoryInterface;
use HiEvents\Enterprise\Licensing\Repository\UsageScope;
use HiEvents\Models\StripeTerminalReader;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<StripeTerminalReaderDomainObject>
 */
class StripeTerminalReaderRepository extends BaseRepository implements StripeTerminalReaderRepositoryInterface
{
    protected function getModel(): string
    {
        return StripeTerminalReader::class;
    }

    public function getDomainObject(): string
    {
        return StripeTerminalReaderDomainObject::class;
    }

    public function existsForLiveOrganizer(?int $accountId): bool
    {
        return $this->runQuery(fn () => UsageScope::liveOrganizer(
            DB::table('stripe_terminal_readers')
                ->join('organizers', 'organizers.id', '=', 'stripe_terminal_readers.organizer_id')
                ->whereNull('stripe_terminal_readers.deleted_at'),
            $accountId,
        )->exists());
    }
}
