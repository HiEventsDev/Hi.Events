import { expect, test } from '@playwright/test';
import { assignScheduleLanes, initialSelectedOccurrence } from '../../../frontend/src/utilites/lineupSchedule';
import { placeEmbeddedDialog } from '../../../frontend/src/components/widget/embeddedDialogPlacement';
import { widgetFrameStyle } from '../../../frontend/src/utilites/widgetTheme';
import { EventOccurrenceStatus, type EventOccurrence } from '../../../frontend/src/types';

const occurrence = (id: number, overrides: Partial<EventOccurrence> = {}): EventOccurrence => ({
  id,
  start_date: `2030-06-${String(10 + id).padStart(2, '0')} 19:00:00`,
  status: EventOccurrenceStatus.ACTIVE,
  is_past: false,
  ...overrides,
});

const channel = (value: number) => {
  const srgb = value / 255;
  return srgb <= 0.03928 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4;
};

const luminance = ([r, g, b]: number[]) => 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);

const hexToRgb = (hex: string) => [1, 3, 5].map(index => parseInt(hex.slice(index, index + 2), 16));

const mixedOver = (mix: string, background: string) => {
  const [, color, percent] = mix.match(/color-mix\(in srgb, (#[0-9a-f]{6}) (\d+)%, transparent\)/i)!;
  const alpha = Number(percent) / 100;
  const foreground = hexToRgb(color);
  return hexToRgb(background).map((base, index) => foreground[index] * alpha + base * (1 - alpha));
};

const contrast = (a: number[], b: number[]) => {
  const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (light + 0.05) / (dark + 0.05);
};

test.describe('public schedule stage lanes', () => {
  test('slots that overlap on one stage sit side by side, and the rest keep the full width', () => {
    const lanes = assignScheduleLanes([
      { id: 1, top: 0, bottom: 480 },
      { id: 2, top: 0, bottom: 800 },
      { id: 3, top: 900, bottom: 960 },
      { id: 4, top: 960, bottom: 988 },
    ]);

    expect(lanes.get(1)).toEqual({ lane: 0, lanes: 2 });
    expect(lanes.get(2)).toEqual({ lane: 1, lanes: 2 });
    expect(lanes.get(3)).toEqual({ lane: 0, lanes: 1 });
    expect(lanes.get(4)).toEqual({ lane: 0, lanes: 1 });
  });

  test('a lane frees up once its slot has ended', () => {
    const lanes = assignScheduleLanes([
      { id: 1, top: 0, bottom: 100 },
      { id: 2, top: 50, bottom: 200 },
      { id: 3, top: 120, bottom: 180 },
    ]);

    expect(lanes.get(1)).toEqual({ lane: 0, lanes: 2 });
    expect(lanes.get(2)).toEqual({ lane: 1, lanes: 2 });
    expect(lanes.get(3)).toEqual({ lane: 0, lanes: 2 });
  });
});

test.describe('initial occurrence on a public event page', () => {
  test('a valid date from the link is used straight away', () => {
    expect(initialSelectedOccurrence([occurrence(1), occurrence(2)], 2)?.id).toBe(2);
  });

  test('a single bookable date is picked without a link', () => {
    const past = occurrence(1, { is_past: true });
    const cancelled = occurrence(2, { status: EventOccurrenceStatus.CANCELLED });
    expect(initialSelectedOccurrence([past, cancelled, occurrence(3)], null)?.id).toBe(3);
  });

  test('several dates and no link, or a link to a date that cannot be booked, picks nothing', () => {
    expect(initialSelectedOccurrence([occurrence(1), occurrence(2)], null)).toBeUndefined();
    expect(initialSelectedOccurrence([occurrence(1), occurrence(2, { is_past: true })], 2)?.id).toBe(1);
    expect(initialSelectedOccurrence([occurrence(1), occurrence(2), occurrence(3, { is_past: true })], 3)).toBeUndefined();
  });
});

test.describe('dialog placement inside an embedded widget', () => {
  test('a dialog in a fully visible short frame asks for more room instead of shrinking for good', () => {
    const placement = placeEmbeddedDialog(200, 370, { top: 0, bottom: 370 });

    expect(placement.yOffset).toBe(16);
    expect(placement.maxHeight).toBe(338);
    expect(placement.frameHeight).toBe(104 + 640 + 16);
  });

  test('once the frame has grown, the dialog uses the part of it the visitor can see', () => {
    const placement = placeEmbeddedDialog(200, 760, { top: 0, bottom: 600 });

    expect(placement.yOffset).toBe(16);
    expect(placement.maxHeight).toBe(568);
  });

  test('when the host page has scrolled past the top of the frame, the dialog opens below the visible top edge', () => {
    const placement = placeEmbeddedDialog(300, 860, { top: 200, bottom: 860 });

    expect(placement.yOffset).toBeGreaterThanOrEqual(216);
    expect(placement.yOffset + placement.maxHeight).toBeLessThanOrEqual(860 - 16);
  });

  test('a tall frame that already fits the dialog asks for no extra room and stays near the click', () => {
    const placement = placeEmbeddedDialog(2000, 3000, { top: 1200, bottom: 2100 });

    expect(placement.frameHeight).toBe(0);
    expect(placement.maxHeight).toBe(640);
    expect(placement.yOffset).toBeGreaterThanOrEqual(1216);
    expect(placement.yOffset + placement.maxHeight).toBeLessThanOrEqual(2100 - 16);
  });

  test('without a measurement the whole frame counts as visible', () => {
    const placement = placeEmbeddedDialog(400, 900, null);

    expect(placement.maxHeight).toBe(640);
    expect(placement.yOffset).toBe(244);
    expect(placement.frameHeight).toBe(0);
  });
});

test.describe('widget theme contrast', () => {
  const tertiaryContrast = (primaryText: string, background: string) => {
    const style = widgetFrameStyle({
      background,
      primary: '#7b5db8',
      primaryText,
      secondary: '#7b5eb9',
      secondaryText: '#ffffff',
      bodyBackground: background,
    }, {}, null) as Record<string, string>;
    return contrast(mixedOver(style['--event-secondary-text-color'], background), hexToRgb(background));
  };

  test('low-emphasis schedule text meets AA on the default widget and on the default event theme', () => {
    expect(tertiaryContrast('#000000', '#ffffff')).toBeGreaterThanOrEqual(4.5);
    expect(tertiaryContrast('#1a1a1a', '#ffffff')).toBeGreaterThanOrEqual(4.5);
    expect(tertiaryContrast('#ffffff', '#1f1f1f')).toBeGreaterThanOrEqual(4.5);
  });
});
