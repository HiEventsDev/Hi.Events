import {expect, test} from '@playwright/test';
import {canAttendeeChangeSeat} from '../../../frontend/src/ee/seating/components/ChangeSeatButton/canAttendeeChangeSeat';
import {type Attendee, EventOccurrenceStatus} from '../../../frontend/src/types';

const seatedAttendee = (overrides: Partial<Attendee> = {}): Attendee => ({
  product_id: 1,
  product_price_id: 1,
  order_id: 1,
  status: 'ACTIVE',
  first_name: 'Ada',
  last_name: 'Guest',
  email: 'ada@example.test',
  public_id: 'A-1',
  short_id: 'a_1',
  seat_uid: 'e2.0.9',
  is_checked_in: false,
  event_occurrence: {
    id: 1,
    event_id: 1,
    start_date: '2099-01-01T19:00:00Z',
    status: EventOccurrenceStatus.ACTIVE,
    is_future: true,
  } as Attendee['event_occurrence'],
  ...overrides,
});

test.describe('change seat visibility', () => {
  test('an active, seated, not checked-in guest on a completed order for an upcoming date can change seat', () => {
    expect(canAttendeeChangeSeat(seatedAttendee(), 'COMPLETED', true)).toBe(true);
  });

  test('self-service must be on', () => {
    expect(canAttendeeChangeSeat(seatedAttendee(), 'COMPLETED', false)).toBe(false);
  });

  test('the order must be completed', () => {
    expect(canAttendeeChangeSeat(seatedAttendee(), 'AWAITING_OFFLINE_PAYMENT', true)).toBe(false);
    expect(canAttendeeChangeSeat(seatedAttendee(), 'CANCELLED', true)).toBe(false);
  });

  test('the guest must hold a seat and be active', () => {
    expect(canAttendeeChangeSeat(seatedAttendee({seat_uid: null}), 'COMPLETED', true)).toBe(false);
    expect(canAttendeeChangeSeat(seatedAttendee({status: 'CANCELLED'}), 'COMPLETED', true)).toBe(false);
    expect(canAttendeeChangeSeat(seatedAttendee({status: 'AWAITING_PAYMENT'}), 'COMPLETED', true)).toBe(false);
  });

  test('a checked-in guest cannot change seat', () => {
    expect(canAttendeeChangeSeat(seatedAttendee({is_checked_in: true}), 'COMPLETED', true)).toBe(false);
  });

  test('the date must not have started or been cancelled', () => {
    const started = seatedAttendee();
    started.event_occurrence = {...started.event_occurrence!, is_future: false};
    const cancelled = seatedAttendee();
    cancelled.event_occurrence = {...cancelled.event_occurrence!, status: EventOccurrenceStatus.CANCELLED};

    expect(canAttendeeChangeSeat(started, 'COMPLETED', true)).toBe(false);
    expect(canAttendeeChangeSeat(cancelled, 'COMPLETED', true)).toBe(false);
    expect(canAttendeeChangeSeat(seatedAttendee({event_occurrence: undefined}), 'COMPLETED', true)).toBe(false);
  });
});
