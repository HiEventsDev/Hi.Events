import { expect, test } from '@playwright/test';
import { checkContrast, getAccentInk, getDerivedColors } from '../../../frontend/src/utilites/themeUtils';

const ACCENTS = ['#8b5cf6', '#ff5a36', '#facc15', '#a3e635', '#22d3ee', '#f97316', '#14b8a6', '#9ec19e', '#1e3a8a', '#111111', '#ffffff'];

test.describe('homepage accent text colour', () => {
  for (const mode of ['light', 'dark'] as const) {
    for (const accent of ACCENTS) {
      test(`${accent} accent text stays readable in ${mode} mode`, () => {
        const ink = getAccentInk(accent, mode);

        expect(checkContrast(ink, getDerivedColors(mode).surface).passesAA).toBe(true);
      });
    }
  }

  test('an accent that is already readable is used as-is', () => {
    expect(getAccentInk('#1e3a8a', 'light')).toBe('#1e3a8a');
  });

  test('a pale accent is darkened rather than replaced, so it keeps its hue', () => {
    const ink = getAccentInk('#facc15', 'light');

    expect(ink).not.toBe('#facc15');
    expect(ink).not.toBe(getDerivedColors('light').textPrimary);
  });

  test('an unreadable colour value falls back to the body text colour', () => {
    expect(getAccentInk('not-a-colour', 'dark')).toBe(getDerivedColors('dark').textPrimary);
  });
});
