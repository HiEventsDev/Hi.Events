<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AnnouncementUserDomainObject;
use HiEvents\Models\AnnouncementUser;
use HiEvents\Repository\Interfaces\AnnouncementUserRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * @extends BaseRepository<AnnouncementUserDomainObject>
 */
class AnnouncementUserRepository extends BaseRepository implements AnnouncementUserRepositoryInterface
{
    protected function getModel(): string
    {
        return AnnouncementUser::class;
    }

    public function getDomainObject(): string
    {
        return AnnouncementUserDomainObject::class;
    }

    public function markSeen(array $announcementIds, int $userId): void
    {
        $now = Carbon::now();

        $this->model->insertOrIgnore(
            collect($announcementIds)->map(fn ($announcementId) => [
                'announcement_id' => $announcementId,
                'user_id' => $userId,
                'first_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
        );
    }

    public function markDismissed(int $announcementId, int $userId): void
    {
        $now = Carbon::now();

        $this->model->upsert(
            [
                [
                    'announcement_id' => $announcementId,
                    'user_id' => $userId,
                    'first_seen_at' => $now,
                    'dismissed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['announcement_id', 'user_id'],
            ['dismissed_at', 'updated_at'],
        );
    }
}
