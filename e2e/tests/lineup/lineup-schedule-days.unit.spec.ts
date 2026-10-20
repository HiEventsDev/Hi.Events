import { expect, test } from '@playwright/test';
import {
  adjacentOccurrenceDay,
  localScheduleDayKey,
  occurrenceDayKeys,
  scheduleCoverage,
} from '../../../frontend/src/components/routes/event/Lineup/ScheduleBuilder/scheduleDays';
import type { EventLineup, LineupEntry, ScheduleItem } from '../../../frontend/src/api/lineup.client';

const entry = (id: number): LineupEntry => ({
  id,
  billing_tier: 'STANDARD',
  role_label: null,
  profile: { id, name: `Person ${id}`, tagline: null, bio: null, links: [], images: [] },
});

const slot = (id: number, startsAt: string, entryIds: number[]): ScheduleItem => ({
  id,
  title: null,
  description: null,
  starts_at: startsAt,
  ends_at: startsAt,
  event_space_id: null,
  lineup_entry_ids: entryIds,
});

test.describe('timetable days for recurring events', () => {
  test('a slot saved after midnight belongs to the evening before', () => {
    expect(localScheduleDayKey('2030-06-15 01:00:00')).toBe('2030-06-14');
    expect(localScheduleDayKey('2030-06-15 04:59:00')).toBe('2030-06-14');
    expect(localScheduleDayKey('2030-06-15 05:00:00')).toBe('2030-06-15');
    expect(localScheduleDayKey('2030-06-14 22:00:00')).toBe('2030-06-14');
    expect(localScheduleDayKey('2030-03-01 02:00:00')).toBe('2030-02-28');
  });

  test('occurrence days cover every timetable day an occurrence spans, once each', () => {
    expect(occurrenceDayKeys([
      { start_date: '2030-06-21 19:00:00', end_date: '2030-06-21 23:00:00' },
      { start_date: '2030-06-14 19:00:00', end_date: '2030-06-15 02:00:00' },
      { start_date: '2030-06-14 21:00:00', end_date: null },
      { start_date: '2030-06-28 10:00:00', end_date: '2030-06-30 18:00:00' },
    ], 'UTC')).toEqual(['2030-06-14', '2030-06-21', '2030-06-28', '2030-06-29', '2030-06-30']);
  });

  test('occurrence days follow the event time zone', () => {
    expect(occurrenceDayKeys([{ start_date: '2030-06-14 23:30:00', end_date: '2030-06-15 01:00:00' }], 'America/New_York'))
      .toEqual(['2030-06-14']);
  });

  test('previous and next step between occurrence days', () => {
    const days = ['2030-06-14', '2030-06-21', '2030-06-28'];

    expect(adjacentOccurrenceDay(days, '2030-06-14', 1)).toBe('2030-06-21');
    expect(adjacentOccurrenceDay(days, '2030-06-17', 1)).toBe('2030-06-21');
    expect(adjacentOccurrenceDay(days, '2030-06-17', -1)).toBe('2030-06-14');
    expect(adjacentOccurrenceDay(days, '2030-06-28', 1)).toBeUndefined();
    expect(adjacentOccurrenceDay(days, '2030-06-14', -1)).toBeUndefined();
  });

  test('people booked only on days with no occurrence are not counted as scheduled', () => {
    const lineup: EventLineup = {
      entries: [entry(1), entry(2), entry(3), entry(4)],
      spaces: [],
      schedule_items: [
        slot(10, '2030-06-14 20:00:00', [1]),
        slot(11, '2030-06-19 20:00:00', [2]),
        slot(12, '2030-06-19 21:00:00', [3]),
        slot(13, '2030-06-21 20:00:00', [3]),
      ],
    };

    expect(scheduleCoverage(lineup, 'UTC', new Set(['2030-06-14', '2030-06-21']))).toEqual({ scheduled: 2, offDateOnly: 1 });
  });

  test('every slot counts when the event has no occurrence days to check against', () => {
    const lineup: EventLineup = {
      entries: [entry(1), entry(2)],
      spaces: [],
      schedule_items: [slot(10, '2030-06-19 20:00:00', [1])],
    };

    expect(scheduleCoverage(lineup, 'UTC')).toEqual({ scheduled: 1, offDateOnly: 0 });
  });
});
