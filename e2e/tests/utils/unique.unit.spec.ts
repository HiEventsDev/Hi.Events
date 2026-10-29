import { expect, test } from '@playwright/test';
import { uniqueShort } from '../../utils/unique';

test.describe('unique test data', () => {
  test('short names are letters only so they always pass the person name check', () => {
    const names = Array.from({ length: 5000 }, () => uniqueShort('Renamed'));

    for (const name of names) {
      expect(name).toMatch(/^Renamed [a-z]{6}$/);
    }
    expect(new Set(names).size).toBeGreaterThan(4990);
  });
});
