<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use HiEvents\DomainObjects\Enums\AnnouncementTargetType;
use HiEvents\DomainObjects\Status\AnnouncementStatus;
use HiEvents\Models\Announcement;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<AnnouncementDomainObject>
 */
class AnnouncementRepository extends BaseRepository implements AnnouncementRepositoryInterface
{
    protected function getModel(): string
    {
        return Announcement::class;
    }

    public function getDomainObject(): string
    {
        return AnnouncementDomainObject::class;
    }

    public function getAnnouncementsWithCounts(?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->runQuery(function () use ($search, $perPage) {
            $query = $this->model
                ->withCount([
                    'announcementUsers as seen_count',
                    'announcementUsers as dismissed_count' => fn ($query) => $query->whereNotNull('dismissed_at'),
                ]);

            if ($search) {
                $query->where('title', 'ilike', "%{$search}%");
            }

            return $query->orderByDesc('id')->paginate($perPage);
        });
    }

    public function findActiveForUser(int $userId, int $accountId): Collection
    {
        return $this->runQuery(function () use ($userId, $accountId) {
            $announcements = $this->model
                ->where('status', AnnouncementStatus::PUBLISHED->name)
                ->where(function ($query) use ($userId, $accountId) {
                    $query
                        ->where('target_type', AnnouncementTargetType::ALL->name)
                        ->orWhere(function ($query) use ($accountId) {
                            $query->where('target_type', AnnouncementTargetType::ACCOUNTS->name)
                                ->whereJsonContains('target_account_ids', $accountId);
                        })
                        ->orWhere(function ($query) use ($userId) {
                            $query->where('target_type', AnnouncementTargetType::USERS->name)
                                ->whereJsonContains('target_user_ids', $userId);
                        });
                })
                ->whereDoesntHave('announcementUsers', function ($query) use ($userId) {
                    $query->where('user_id', $userId)->whereNotNull('dismissed_at');
                })
                ->orderByDesc('id')
                ->get();

            return $this->handleResults($announcements, AnnouncementDomainObject::class);
        });
    }
}
