<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment;

use HiEvents\DomainObjects\Generated\CrmSegmentDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSegmentRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentUsageDTO;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;
use Throwable;

class DeleteSegmentHandler
{
    public function __construct(
        private readonly CrmSegmentRepositoryInterface $segmentRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function handle(int $organizerId, int $segmentId): void
    {
        $this->databaseManager->transaction(function () use ($organizerId, $segmentId): void {
            $blocking = $this->segmentRepository->findUsages($organizerId, [$segmentId])
                ->filter(static fn (SegmentUsageDTO $usage) => $usage->blocks_deletion);

            if ($blocking->isNotEmpty()) {
                throw new ResourceConflictException(__('This segment is still in use: :usages. Cancel the campaign or change who the automation sends to first.', [
                    'usages' => $blocking->map(fn (SegmentUsageDTO $usage) => $this->describe($usage))->implode(', '),
                ]));
            }

            $deleted = $this->segmentRepository->deleteWhere([
                CrmSegmentDomainObjectAbstract::ID => $segmentId,
                CrmSegmentDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            ]);

            if ($deleted === 0) {
                throw new ResourceNotFoundException(__('Segment not found'));
            }
        });
    }

    private function describe(SegmentUsageDTO $usage): string
    {
        if ($usage->type === SegmentUsageDTO::TYPE_CAMPAIGN) {
            return __('Scheduled campaign ":name"', ['name' => $usage->name]);
        }

        return __('Automation ":name"', ['name' => $usage->name]);
    }
}
