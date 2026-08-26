<?php

namespace HiEvents\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\AnnouncementTargetType;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\Announcement;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Announcement\DTO\GetAllAnnouncementsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllAnnouncementsHandler
{
    public function __construct(
        private readonly AnnouncementRepositoryInterface $announcementRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function handle(GetAllAnnouncementsDTO $dto): LengthAwarePaginator
    {
        $announcements = $this->announcementRepository->getAnnouncementsWithCounts($dto->search, $dto->perPage);

        $accountNames = $this->resolveAccountNames($announcements->items());
        $userNames = $this->resolveUserNames($announcements->items());

        foreach ($announcements->items() as $announcement) {
            $announcement->setAttribute('target_names', match ($announcement->target_type) {
                AnnouncementTargetType::ACCOUNTS->name => collect($announcement->target_account_ids)
                    ->mapWithKeys(fn ($id) => [$id => $accountNames[$id] ?? "#{$id}"])->all(),
                AnnouncementTargetType::USERS->name => collect($announcement->target_user_ids)
                    ->mapWithKeys(fn ($id) => [$id => $userNames[$id] ?? "#{$id}"])->all(),
                default => [],
            });
        }

        return $announcements;
    }

    private function resolveAccountNames(array $announcements): array
    {
        $accountIds = collect($announcements)
            ->where('target_type', AnnouncementTargetType::ACCOUNTS->name)
            ->flatMap(fn (Announcement $announcement) => $announcement->target_account_ids ?? [])
            ->unique()
            ->values();

        if ($accountIds->isEmpty()) {
            return [];
        }

        return $this->accountRepository
            ->findWhereIn('id', $accountIds->all())
            ->mapWithKeys(fn (AccountDomainObject $account) => [$account->getId() => $account->getName()])
            ->all();
    }

    private function resolveUserNames(array $announcements): array
    {
        $userIds = collect($announcements)
            ->where('target_type', AnnouncementTargetType::USERS->name)
            ->flatMap(fn (Announcement $announcement) => $announcement->target_user_ids ?? [])
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        return $this->userRepository
            ->includeDeleted()
            ->findWhereIn('id', $userIds->all())
            ->mapWithKeys(fn (UserDomainObject $user) => [
                $user->getId() => $user->getDeletedAt() === null
                    ? $user->getFullName()
                    : $user->getFullName().' '.__('(deactivated)'),
            ])
            ->all();
    }
}
