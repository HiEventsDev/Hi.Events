import { fileURLToPath } from 'node:url';
import type { Locator } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { LineupPage } from '../../pages/lineup.page';
import { uniqueName } from '../../utils/unique';

const COVER_PHOTO = fileURLToPath(new URL('../../fixtures/assets/event-cover.png', import.meta.url));
const GALLERY_PHOTO = fileURLToPath(new URL('../../fixtures/assets/gallery-photo.png', import.meta.url));

const liveRegion = (scope: Locator) => scope.locator('[id^="DndLiveRegion"]');

test.describe('sortable list announcements', () => {
  test('picking up a person keeps the pickup announcement until it moves', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const first = uniqueName('Opener');
    const second = uniqueName('Closer');
    for (const name of [first, second]) {
      const profile = await api.createLineupProfile(account.organizerId, { name });
      await api.addLineupEntry(event.eventId, { profile_id: profile.id, billing_tier: 'STANDARD' });
    }

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);
    const rail = page.getByTestId('lineup-people-list').locator('xpath=..');
    await page.getByRole('button', { name: `Drag to reorder ${first}` }).focus();
    await page.keyboard.press('Space');
    await expect(liveRegion(rail)).toHaveText(`Picked up ${first}, position 1 of 2.`);

    await expect(async () => {
      await page.keyboard.press('Escape');
      await expect(liveRegion(rail)).toHaveText(`Move cancelled. ${first} is back in its original position.`, { timeout: 1000 });
    }).toPass();
  });

  test('the photo grid announces photos by position, not by internal id', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const name = uniqueName('Photo Voice');
    const profile = await api.createLineupProfile(account.organizerId, { name });
    await api.addLineupEntry(event.eventId, { profile_id: profile.id, billing_tier: 'HEADLINER' });

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);
    await lineup.person(name).getByRole('button', { name: new RegExp(name) }).filter({ hasText: name }).click();
    await lineup.uploadPhoto(COVER_PHOTO);
    await expect(lineup.drawer().getByRole('button', { name: 'Remove photo 1' })).toBeVisible();
    await lineup.uploadPhoto(GALLERY_PHOTO);

    const grip = lineup.drawer().getByRole('button', { name: 'Move photo 2 of 2' });
    await expect(grip).toBeVisible();
    await expect(grip).toHaveAccessibleDescription(/^To move this item, press space or enter\./);

    const announcements = liveRegion(lineup.drawer());
    const photos = lineup.drawer().getByTestId('lineup-profile-photos').locator('[data-testid^="lineup-profile-photo-"]:not([data-testid$="-dropzone"])');
    const sortRequest = page.waitForRequest(request => /\/profiles\/\d+\/images\/sort/.test(request.url()));
    await grip.focus();
    await page.keyboard.press('Space');
    await expect(announcements).toHaveText('Picked up Photo 2, position 2 of 2.');

    await expect(async () => {
      await page.keyboard.press('ArrowLeft');
      await expect(announcements).toHaveText('Photo 2 moved to position 1 of 2.', { timeout: 1000 });
    }).toPass();
    await page.keyboard.press('Space');
    await sortRequest;
    await expect(announcements).toHaveText('Photo 2 dropped at position 1 of 2.');
    await expect(photos.nth(0)).toContainText('Main photo');
  });
});
