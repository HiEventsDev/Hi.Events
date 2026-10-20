import { expect, test } from '@playwright/test';
import { groupScheduleByDay, lineupForOccurrence, scheduleDayKey } from '../../../frontend/src/utilites/lineupSchedule';
import type { EventLineup, LineupEntry, ScheduleItem } from '../../../frontend/src/api/lineup.client';

const entry = (id: number): LineupEntry => ({
  id,
  billing_tier: 'STANDARD',
  role_label: null,
  profile: { id, name: `Person ${id}`, tagline: null, bio: null, links: [], images: [] },
});

const slot = (id: number, startsAt: string, endsAt: string, entryIds: number[]): ScheduleItem => ({
  id,
  title: null,
  description: null,
  starts_at: startsAt,
  ends_at: endsAt,
  event_space_id: null,
  lineup_entry_ids: entryIds,
});

test.describe('lineup schedule grouping', () => {
  test('sets that run past midnight stay on the night they started', () => {
    expect(scheduleDayKey('2030-06-15 01:30:00', 'UTC')).toBe('2030-06-14');
    expect(scheduleDayKey('2030-06-15 05:00:00', 'UTC')).toBe('2030-06-15');
    expect(scheduleDayKey('2030-06-14 23:30:00', 'Europe/Dublin')).toBe('2030-06-14');
    expect(scheduleDayKey('2030-06-15 04:30:00', 'Europe/Dublin')).toBe('2030-06-15');
  });

  test('groups slots into days in start order', () => {
    const days = groupScheduleByDay([
      slot(2, '2030-06-14 22:00:00', '2030-06-15 01:00:00', []),
      slot(3, '2030-06-15 02:00:00', '2030-06-15 03:00:00', []),
      slot(1, '2030-06-14 19:00:00', '2030-06-14 20:00:00', []),
      slot(4, '2030-06-15 19:00:00', '2030-06-15 20:00:00', []),
    ], 'UTC');

    expect(days.map(day => day.key)).toEqual(['2030-06-14', '2030-06-15']);
    expect(days[0].items.map(item => item.id)).toEqual([1, 2, 3]);
    expect(days[1].items.map(item => item.id)).toEqual([4]);
  });

  test('a recurring date shows its own slots plus people with no slots at all', () => {
    const lineup: EventLineup = {
      entries: [entry(1), entry(2), entry(3)],
      spaces: [],
      schedule_items: [
        slot(10, '2030-06-14 20:00:00', '2030-06-14 21:00:00', [1]),
        slot(11, '2030-06-21 20:00:00', '2030-06-21 21:00:00', [2]),
      ],
    };

    const firstWeek = lineupForOccurrence(lineup, { start_date: '2030-06-14 19:00:00', end_date: '2030-06-14 23:00:00' }, 'UTC');

    expect(firstWeek.schedule_items.map(item => item.id)).toEqual([10]);
    expect(firstWeek.entries.map(candidate => candidate.id)).toEqual([1, 3]);
  });

  test('a hidden schedule still limits each date to the people booked on it', () => {
    const lineup: EventLineup = {
      entries: [
        { ...entry(1), schedule_days: ['2030-06-14', '2030-06-28'] },
        { ...entry(2), schedule_days: ['2030-06-21'] },
        { ...entry(3), schedule_days: [] },
        entry(4),
      ],
      spaces: [],
      schedule_items: [],
    };

    const firstWeek = lineupForOccurrence(lineup, { start_date: '2030-06-14 19:00:00', end_date: '2030-06-15 02:00:00' }, 'UTC');
    expect(firstWeek.entries.map(candidate => candidate.id)).toEqual([1, 3, 4]);
    expect(firstWeek.schedule_items).toEqual([]);

    const secondWeek = lineupForOccurrence(lineup, { start_date: '2030-06-22 00:30:00', end_date: null }, 'America/New_York');
    expect(secondWeek.entries.map(candidate => candidate.id)).toEqual([2, 3, 4]);

    const weekend = lineupForOccurrence(lineup, { start_date: '2030-06-27 18:00:00', end_date: '2030-06-29 23:00:00' }, 'UTC');
    expect(weekend.entries.map(candidate => candidate.id)).toEqual([1, 3, 4]);
  });

  test('clock changes use the local wall clock for the 05:00 boundary', () => {
    expect(scheduleDayKey('2030-10-27 04:30:00', 'Europe/Dublin')).toBe('2030-10-26');
    expect(scheduleDayKey('2030-10-27 05:00:00', 'Europe/Dublin')).toBe('2030-10-27');
    expect(scheduleDayKey('2030-03-31 04:30:00', 'Europe/Dublin')).toBe('2030-03-31');
    expect(scheduleDayKey('2030-03-31 02:30:00', 'Europe/Dublin')).toBe('2030-03-30');
    expect(scheduleDayKey('2030-03-01 04:00:00', 'Europe/Dublin')).toBe('2030-02-28');
  });
});
