<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Repository;

use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class UsageScope
{
    public static function upcomingEvent(Builder $query, ?int $accountId): Builder
    {
        return $query
            ->whereNull('events.deleted_at')
            ->where(static fn (Builder $events) => $events
                ->whereNull('events.status')
                ->orWhere('events.status', '!=', EventStatus::ARCHIVED->name))
            ->when($accountId !== null, static fn (Builder $events) => $events->where('events.account_id', $accountId))
            ->where(static fn (Builder $events) => $events
                ->whereNotExists(static fn (Builder $occurrences) => self::occurrencesOfEvent($occurrences))
                ->orWhereExists(static fn (Builder $occurrences) => self::occurrencesOfEvent($occurrences)
                    ->where('event_occurrences.status', '!=', EventOccurrenceStatus::CANCELLED->name)
                    ->whereRaw('COALESCE(event_occurrences.end_date, event_occurrences.start_date) >= ?', [now()])));
    }

    public static function liveOrganizer(Builder $query, ?int $accountId): Builder
    {
        return $query
            ->whereNull('organizers.deleted_at')
            ->when($accountId !== null, static fn (Builder $organizers) => $organizers->where('organizers.account_id', $accountId));
    }

    private static function occurrencesOfEvent(Builder $occurrences): Builder
    {
        return $occurrences
            ->select(DB::raw(1))
            ->from('event_occurrences')
            ->whereColumn('event_occurrences.event_id', 'events.id')
            ->whereNull('event_occurrences.deleted_at');
    }
}
