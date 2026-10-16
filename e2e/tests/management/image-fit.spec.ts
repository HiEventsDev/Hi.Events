import fs from 'node:fs';
import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createFreshOrganizer, createLiveEventWithFreeTicket } from '../../api/factory';
import { stressImageFit } from '../../utils/imageFit';

const PHOTO = fs.readFileSync(new URL('../../fixtures/assets/gallery-photo.png', import.meta.url));

const upload = async (page: Page, type: string, entityId: number): Promise<number> => {
  const response = await page.request.post('/api/images', {
    headers: { Accept: 'application/json' },
    multipart: { image: { name: 'photo.png', mimeType: 'image/png', buffer: PHOTO }, image_type: type, entity_id: String(entityId) },
  });
  expect(response.ok(), `${type} upload`).toBeTruthy();
  return (await response.json()).data.id;
};

const expectImagesToFit = async (page: Page, label: string) => {
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);
  const report = await stressImageFit(page);
  expect(report.images, `${label}: images to check`).toBeGreaterThan(0);
  expect(report.problems, `${label}: images spilling out of their containers`).toEqual([]);
  expect(report.horizontalOverflow, `${label}: horizontal page scroll`).toBeLessThanOrEqual(0);
};

test.describe('image containers', () => {
  test('photos, gallery images and sponsor logos of any shape stay inside their boxes', async ({ authedPage: page, api }) => {
    test.setTimeout(180_000);
    const organizer = await createFreshOrganizer(api, 'Image Fit Org');
    await api.updateOrganizerStatus(organizer.id, 'LIVE');
    const event = await createLiveEventWithFreeTicket(api, organizer.id);
    await api.importLineup(event.eventId, {
      people: [{ name: 'Velvet Harbour', billing_tier: 'HEADLINER' }, { name: 'Ada Byron', billing_tier: 'FEATURED' }, { name: 'Opener' }],
      slots: [{ starts_at: '2030-06-14 21:30:00', ends_at: '2030-06-14 23:00:00', stage: 'Main Stage', people: ['Velvet Harbour', 'Ada Byron'] }],
    });
    const lineup = await api.getLineup(event.eventId);
    for (const entry of lineup.entries) {
      await upload(page, 'PROFILE_IMAGE', entry.profile.id);
      await upload(page, 'PROFILE_IMAGE', entry.profile.id);
    }
    const gallery = [await upload(page, 'EVENT_GALLERY', event.eventId), await upload(page, 'EVENT_GALLERY', event.eventId), await upload(page, 'EVENT_GALLERY', event.eventId)];
    const sponsors = [await upload(page, 'EVENT_SPONSOR_LOGO', event.eventId), await upload(page, 'EVENT_SPONSOR_LOGO', event.eventId), await upload(page, 'EVENT_SPONSOR_LOGO', event.eventId)];
    await api.updateEventSettings(event.eventId, {
      homepage_sections: [
        { id: 'tickets', type: 'TICKETS', enabled: true },
        { id: 'lineup', type: 'LINEUP', enabled: true },
        { id: 'schedule', type: 'SCHEDULE', enabled: true },
        { id: 'gallery', type: 'GALLERY', enabled: true, image_ids: gallery },
        { id: 'sponsors', type: 'SPONSORS', enabled: true, sponsors: sponsors.map((id, index) => ({ image_id: id, name: `Sponsor ${index}` })) },
      ],
    });

    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: 900 });

      await page.goto(`/event/${event.eventId}/${event.slug}`);
      await expectImagesToFit(page, `${width} event page`);

      await page.getByTestId('event-lineup-section').getByRole('button', { name: 'About Velvet Harbour' }).click();
      await expectImagesToFit(page, `${width} profile popup`);

      await page.goto(`/manage/event/${event.eventId}/lineup`);
      await expectImagesToFit(page, `${width} lineup workspace`);

      await page.goto(`/manage/organizer/${organizer.id}/people`);
      await expectImagesToFit(page, `${width} people page`);

      await page.goto(`/manage/event/${event.eventId}/homepage-designer`);
      for (const type of ['gallery', 'sponsors']) {
        await page.getByTestId(`homepage-section-row-${type}`).getByRole('button', { name: 'Section settings' }).click();
      }
      await expectImagesToFit(page, `${width} homepage designer`);
    }
  });
});
