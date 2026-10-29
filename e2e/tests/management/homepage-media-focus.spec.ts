import fs from 'node:fs';
import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { waitForHydration } from '../../utils/hydration';

const PHOTO = fs.readFileSync(new URL('../../fixtures/assets/gallery-photo.png', import.meta.url));

const uploadGalleryPhoto = async (page: Page, eventId: number): Promise<number> => {
  const response = await page.request.post('/api/images', {
    headers: { Accept: 'application/json' },
    multipart: { image: { name: 'photo.png', mimeType: 'image/png', buffer: PHOTO }, image_type: 'EVENT_GALLERY', entity_id: String(eventId) },
  });
  expect(response.ok()).toBeTruthy();
  return (await response.json()).data.id;
};

test.describe('homepage media and keyboard focus', () => {
  test('closing the photo viewer returns focus to the photo, and playing a video moves focus to the player', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const photos = [await uploadGalleryPhoto(page, event.eventId), await uploadGalleryPhoto(page, event.eventId)];
    await api.updateEventSettings(event.eventId, {
      homepage_sections: [
        { id: 'gallery', type: 'GALLERY', enabled: true, image_ids: photos },
        { id: 'video', type: 'VIDEO', enabled: true, video_url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' },
      ],
    });
    await page.route('https://www.youtube-nocookie.com/**', route => route.fulfill({ contentType: 'text/html', body: '<html><body></body></html>' }));

    await page.goto(`/event/${event.eventId}/${event.slug}`);
    await waitForHydration(page, '[data-testid="homepage-section-gallery"] button');

    const secondPhoto = page.getByTestId('homepage-section-gallery').getByRole('button', { name: 'Open photo 2' });
    await secondPhoto.focus();
    await page.keyboard.press('Enter');
    const viewer = page.getByRole('dialog', { name: 'Photos' });
    await expect(viewer).toContainText('2 / 2');
    await page.keyboard.press('ArrowRight');
    await expect(viewer).toContainText('1 / 2');
    await page.keyboard.press('Escape');
    await expect(viewer).toBeHidden();
    await expect(secondPhoto).toBeFocused();

    const video = page.getByTestId('homepage-section-video');
    await video.getByRole('button', { name: 'Plays from YouTube' }).focus();
    await page.keyboard.press('Enter');
    await expect(video.locator('iframe')).toBeFocused();
  });
});
