import fs from 'node:fs';
import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { PublicEventPage } from '../../pages/public-event.page';
import { uniqueName } from '../../utils/unique';

const PHOTO = fs.readFileSync(new URL('../../fixtures/assets/gallery-photo.png', import.meta.url));

const uploadProfilePhoto = async (page: Page, profileId: number): Promise<void> => {
  const response = await page.request.post('/api/images', {
    headers: { Accept: 'application/json' },
    multipart: { image: { name: 'photo.png', mimeType: 'image/png', buffer: PHOTO }, image_type: 'PROFILE_IMAGE', entity_id: String(profileId) },
  });
  expect(response.ok()).toBeTruthy();
};

const box = async (locator: Locator) => (await locator.boundingBox())!;

const cardParts = (card: Locator) => ({
  media: card.locator(':scope > span').first(),
  body: card.locator(':scope > span').last(),
});

test.describe('public lineup cards with and without photos', () => {
  test('a photo-less headliner or featured act lines up with its neighbour that has a photo', async ({ page, authedPage, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const names = {
      headlinerPhoto: uniqueName('Photo Headliner'),
      headlinerPlain: uniqueName('Plain Headliner'),
      featuredPhoto: uniqueName('Photo Featured'),
      featuredPlain: uniqueName('Plain Featured'),
    };
    for (const [key, name] of Object.entries(names)) {
      const profile = await api.createLineupProfile(account.organizerId, { name, tagline: 'Live set' });
      if (key.endsWith('Photo')) {
        await uploadProfilePhoto(authedPage, profile.id);
      }
      await api.addLineupEntry(event.eventId, { profile_id: profile.id, billing_tier: key.startsWith('headliner') ? 'HEADLINER' : 'FEATURED' });
    }

    const card = (name: string) => page.getByTestId('event-lineup-card').filter({ hasText: name });

    for (const viewport of [{ width: 768, height: 1024 }, { width: 1440, height: 900 }]) {
      await page.setViewportSize(viewport);
      await new PublicEventPage(page).goto(event.eventId, event.slug);

      const photoHeadliner = await box(card(names.headlinerPhoto));
      const plainHeadliner = await box(card(names.headlinerPlain));
      expect(Math.abs(plainHeadliner.y - photoHeadliner.y)).toBeLessThan(1);
      expect(Math.abs(plainHeadliner.height - photoHeadliner.height)).toBeLessThan(1);
      const plainHeadlinerBody = await box(cardParts(card(names.headlinerPlain)).body);
      expect(plainHeadliner.y + plainHeadliner.height - (plainHeadlinerBody.y + plainHeadlinerBody.height)).toBeLessThan(2);
      const photoMedia = await box(cardParts(card(names.headlinerPhoto)).media);
      const photoBody = await box(cardParts(card(names.headlinerPhoto)).body);
      expect(photoBody.y + photoBody.height).toBeLessThanOrEqual(photoMedia.y + photoMedia.height);

      const photoFeaturedMedia = await box(cardParts(card(names.featuredPhoto)).media);
      const plainFeaturedMedia = await box(cardParts(card(names.featuredPlain)).media);
      expect(Math.abs(plainFeaturedMedia.height - photoFeaturedMedia.height)).toBeLessThan(1);
    }

    await page.setViewportSize({ width: 390, height: 844 });
    await new PublicEventPage(page).goto(event.eventId, event.slug);
    const phonePhoto = await box(card(names.headlinerPhoto));
    const phonePlain = await box(card(names.headlinerPlain));
    expect(Math.abs(phonePlain.y - phonePhoto.y)).toBeLessThan(1);
    expect(Math.abs(phonePlain.height - phonePhoto.height)).toBeLessThan(1);
    expect(phonePlain.width).toBeLessThan(200);
  });
});
