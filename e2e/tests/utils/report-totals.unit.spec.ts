import { expect, test } from '@playwright/test';
import { percentOf, sharedCurrency, sortRows, sumColumn } from '../../../frontend/src/utilites/reportTotals';
import { toCsv } from '../../../frontend/src/utilites/csv';

test.describe('report totals', () => {
  test('sums decimal strings without floating point drift', () => {
    const rows = [{ gross: '0.10' }, { gross: '0.20' }, { gross: '19.99' }, { gross: null }, { gross: 'n/a' }];

    expect(sumColumn(rows, 'gross')).toBe(20.29);
    expect(sumColumn([], 'gross' as never)).toBe(0);
  });

  test('only reports a shared currency when every row uses the same one', () => {
    const currencyOf = (row: { currency: string | null }) => row.currency;

    expect(sharedCurrency([{ currency: 'EUR' }, { currency: 'eur' }], currencyOf)).toBe('EUR');
    expect(sharedCurrency([{ currency: 'EUR' }, { currency: 'GBP' }], currencyOf)).toBeNull();
    expect(sharedCurrency([], currencyOf)).toBeNull();
  });

  test('a rate with nothing to divide by is zero, not NaN', () => {
    expect(percentOf(1, 3)).toBe(33.3);
    expect(percentOf(5, 0)).toBe(0);
  });

  test('sorts numeric strings as numbers, text without case, and keeps blanks last', () => {
    const rows = [{ name: 'beta', total: '9.00' }, { name: 'Alpha', total: '100.00' }, { name: 'gamma', total: null }];

    expect(sortRows(rows, 'total', 'desc').map(row => row.name)).toEqual(['Alpha', 'beta', 'gamma']);
    expect(sortRows(rows, 'total', 'asc').map(row => row.name)).toEqual(['beta', 'Alpha', 'gamma']);
    expect(sortRows(rows, 'name', 'asc').map(row => row.name)).toEqual(['Alpha', 'beta', 'gamma']);
    expect(sortRows(rows, null, 'asc')).toBe(rows);
  });
});

test.describe('report CSV', () => {
  test('quotes cells that would break a row and leaves plain ones alone', () => {
    expect(toCsv(['Product', 'Units'], [['VIP, "front row"', 2], ['Line\nbreak', null]]))
      .toBe('Product,Units\r\n"VIP, ""front row""",2\r\n"Line\nbreak",');
  });

  test('neutralises cells a spreadsheet would run as a formula, but not negative amounts', () => {
    expect(toCsv(['Name', 'Amount'], [['=SUM(A1:A9)', '-5.00'], ['@handle', '+3']]))
      .toBe("Name,Amount\r\n'=SUM(A1:A9),-5.00\r\n'@handle,+3");
  });
});
