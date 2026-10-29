import { expect, test } from '@playwright/test';
import { decodeHtmlEntities } from '../../../frontend/src/utilites/htmlEntities';
import { groupConsecutive } from '../../../frontend/src/utilites/groupConsecutive';

test.describe('event card description preview', () => {
  test('named and numeric entities from the stripped description read as plain text', () => {
    expect(decodeHtmlEntities('Statutory &amp; mandatory &#8212; &quot;Level 3&quot; &#x2014; it&#39;s free&nbsp;today'))
      .toBe('Statutory & mandatory — "Level 3" — it\'s free\u00a0today');
  });

  test('an escaped tag stays text and is decoded only once', () => {
    expect(decodeHtmlEntities('&lt;script&gt;alert(1)&lt;/script&gt; &amp;lt;b&amp;gt;'))
      .toBe('<script>alert(1)</script> &lt;b&gt;');
  });

  test('unknown or out-of-range entities are left alone', () => {
    expect(decodeHtmlEntities('&copyright; &#0; &#x110000; Fish & chips')).toBe('&copyright; &#0; &#x110000; Fish & chips');
  });
});

test.describe('organizer event list month groups', () => {
  const event = (id: number, month: string) => ({ id, month });
  const groups = (events: ReturnType<typeof event>[]) => groupConsecutive(events, (item) => item.month)
    .map((group) => ({ key: group.key, ids: group.items.map((item) => item.id) }));

  test('consecutive events in the same month share a heading, in the order the API sorted them', () => {
    expect(groups([event(1, '2026-10'), event(2, '2026-10'), event(3, '2026-11'), event(4, '2027-01')])).toEqual([
      { key: '2026-10', ids: [1, 2] },
      { key: '2026-11', ids: [3] },
      { key: '2027-01', ids: [4] },
    ]);
  });

  test('past events sorted newest first group the same way, and undated events get their own group', () => {
    expect(groups([event(1, '2026-09'), event(2, '2026-09'), event(3, '2026-08'), event(4, '')])).toEqual([
      { key: '2026-09', ids: [1, 2] },
      { key: '2026-08', ids: [3] },
      { key: '', ids: [4] },
    ]);
  });

  test('an empty page has no groups', () => {
    expect(groups([])).toEqual([]);
  });
});
