import { expect, test } from '@playwright/test';
import { checkContrast, getContrastColor } from '../../../frontend/src/utilites/themeUtils';

test.describe('theme button text colour', () => {
  for (const accent of ['#ef4444', '#3b82f6', '#ec4899', '#22c55e', '#f59e0b', '#7c3aed', '#1e40af', '#000000', '#ffffff']) {
    test(`text on ${accent} is the more readable of the two text colours`, () => {
      const chosen = getContrastColor(accent);
      const other = chosen === '#ffffff' ? '#1a1a1a' : '#ffffff';

      expect(checkContrast(chosen, accent).ratio).toBeGreaterThanOrEqual(checkContrast(other, accent).ratio);
    });
  }

  test('red and mid-blue accents that failed with white text now pass AA', () => {
    expect(checkContrast(getContrastColor('#ef4444'), '#ef4444').passesAA).toBe(true);
    expect(checkContrast(getContrastColor('#3b82f6'), '#3b82f6').passesAA).toBe(true);
  });

  test('accents that already passed keep their text colour', () => {
    expect(getContrastColor('#7c3aed')).toBe('#ffffff');
    expect(getContrastColor('#1e40af')).toBe('#ffffff');
    expect(getContrastColor('#22c55e')).toBe('#1a1a1a');
    expect(getContrastColor('#f5f3ff')).toBe('#1a1a1a');
  });

  test('the default accent keeps white text', () => {
    expect(getContrastColor('#8b5cf6')).toBe('#ffffff');
  });

  test('an unparsable colour keeps white text', () => {
    expect(getContrastColor('var(--accent)')).toBe('#ffffff');
  });
});
