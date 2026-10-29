import { expect, test } from '@playwright/test';
import { joinDateAndTime } from '../../../frontend/src/components/common/DateTimeField/dateTimeValue';

test.describe('date time field default time', () => {
  test('a date picked with no time on an end field closes at the end of that day', () => {
    expect(joinDateAndTime('2030-12-24', '', '23:59')).toBe('2030-12-24T23:59');
  });

  test('a date picked with no time on a start field opens at midnight', () => {
    expect(joinDateAndTime('2030-12-24', '', '00:00')).toBe('2030-12-24T00:00');
  });

  test('changing the date of a saved value keeps its time, including midnight', () => {
    expect(joinDateAndTime('2030-12-25', '19:30', '23:59')).toBe('2030-12-25T19:30');
    expect(joinDateAndTime('2030-12-25', '00:00', '23:59')).toBe('2030-12-25T00:00');
  });

  test('a time chosen before the date is kept', () => {
    expect(joinDateAndTime('2030-12-24', '08:15', '23:59')).toBe('2030-12-24T08:15');
  });

  test('clearing the date clears the value', () => {
    expect(joinDateAndTime('', '19:30', '23:59')).toBe('');
    expect(joinDateAndTime('', '', '23:59')).toBe('');
  });
});
