<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAdminStatsDTO;

class GetAdminStatsHandler
{
    public function __construct(
        private readonly UserRepositoryInterface     $userRepository,
        private readonly AccountRepositoryInterface  $accountRepository,
        private readonly EventRepositoryInterface    $eventRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    )
    {
    }

    public function handle(): GetAdminStatsDTO
    {
        $totalUsers = $this->userRepository->countWhere([]);
        $totalAccounts = $this->accountRepository->countWhere([]);
        $totalLiveEvents = $this->eventRepository->countWhere(['status' => EventStatus::LIVE->name]);
        $totalTicketsSold = $this->attendeeRepository->countWhere(['status' => AttendeeStatus::ACTIVE->name]);

        return new GetAdminStatsDTO(
            total_users: $totalUsers,
            total_accounts: $totalAccounts,
            total_live_events: $totalLiveEvents,
            total_tickets_sold: $totalTicketsSold,
        );
    }
}
