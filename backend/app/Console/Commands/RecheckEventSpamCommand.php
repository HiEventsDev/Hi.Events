<?php

namespace HiEvents\Console\Commands;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Jobs\Event\EventSpamCheckJob;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Console\Command;

class RecheckEventSpamCommand extends Command
{
    protected $signature = 'events:recheck-spam
                            {--account-id= : Only recheck events belonging to this account}
                            {--dry-run : Report what would be rechecked without dispatching}';

    protected $description = 'Discard automated CLEAN spam-check results and rerun the check against live events';

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventSpamCheckRepositoryInterface $eventSpamCheckRepository,
        private readonly EventSpamCheckService $eventSpamCheckService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->eventSpamCheckService->isEnabled()) {
            $this->error('Event spam checking is not enabled.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $accountId = $this->option('account-id');

        $where = ['status' => EventStatus::LIVE->name];

        if ($accountId !== null) {
            $where['account_id'] = (int) $accountId;
        }

        $events = $this->eventRepository->findWhere($where);

        $this->info(sprintf('Found %d live event(s) to recheck.', $events->count()));

        if ($dryRun) {
            $this->warn('DRY RUN MODE - no checks cleared and no jobs dispatched');

            return self::SUCCESS;
        }

        /** @var EventDomainObject $event */
        foreach ($events as $event) {
            $this->eventSpamCheckRepository->deleteWhere([
                'event_id' => $event->getId(),
                'status' => EventSpamCheckStatus::CLEAN->name,
            ]);

            EventSpamCheckJob::dispatch($event->getId());
        }

        $this->info('Recheck dispatched.');

        return self::SUCCESS;
    }
}
