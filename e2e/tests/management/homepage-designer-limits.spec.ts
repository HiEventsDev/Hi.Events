import fs from 'node:fs';
import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { DesignerPage } from '../../pages/designer.page';

const LOGO = fs.readFileSync(new URL('../../fixtures/assets/gallery-photo.png', import.meta.url));

const uploadSponsorLogo = async (page: Page, eventId: number): Promise<number> => {
  const response = await page.request.post('/api/images', {
    headers: { Accept: 'application/json' },
    multipart: { image: { name: 'logo.png', mimeType: 'image/png', buffer: LOGO }, image_type: 'EVENT_SPONSOR_LOGO', entity_id: String(eventId) },
  });
  expect(response.ok(), 'sponsor logo upload').toBeTruthy();
  return (await response.json()).data.id;
};

test.describe('homepage designer limits', () => {
  test('save is held back and the field is shown while an FAQ answer is over the limit', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const designer = new DesignerPage(page);
    await designer.gotoHomepageDesigner(event.eventId);

    const faq = await designer.addSection('faq');
    await faq.getByTestId('homepage-faq-add').click();
    await faq.getByLabel('Question 1').fill('Is there parking?');
    await faq.getByLabel('Answer 1').fill('a'.repeat(5001));
    await expect(faq.getByText('This answer is too long. Shorten it before saving.')).toBeVisible();
    await faq.getByRole('button', { name: 'Section settings for FAQ' }).click();
    await expect(faq.getByLabel('Answer 1')).toHaveCount(0);

    const settingsWrites: string[] = [];
    page.on('request', (request) => {
      if (request.method() !== 'GET' && request.url().includes(`/events/${event.eventId}/settings`)) {
        settingsWrites.push(request.url());
      }
    });

    await designer.saveButton.click();

    await expect(faq.getByLabel('Answer 1')).toBeFocused();
    await expect(faq.getByText('This answer is too long. Shorten it before saving.')).toBeVisible();
    await expect(designer.unsavedStatus).toBeVisible();
    expect(settingsWrites).toEqual([]);

    await faq.getByLabel('Answer 1').fill('Yes, free parking opens two hours before doors.');
    await designer.saveButton.click();
    await expect(designer.savedStatus).toBeVisible();
    expect(settingsWrites).toHaveLength(1);

    const settings = await api.getEventSettings(event.eventId);
    const sections = settings.homepage_sections as { type: string; faqs?: { answer: string }[] }[];
    expect(sections.find((section) => section.type === 'FAQ')?.faqs?.[0]?.answer).toBe('<p>Yes, free parking opens two hours before doors.</p>');
  });

  test('save is held back while a pasted Text section body is over the limit', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const designer = new DesignerPage(page);
    await designer.gotoHomepageDesigner(event.eventId);

    const text = await designer.addSection('text');
    const body = text.locator('[contenteditable="true"]');
    await body.click();
    await body.evaluate((element, pasted) => {
      const data = new DataTransfer();
      data.setData('text/plain', pasted);
      element.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }));
    }, 'a'.repeat(50001));
    const limitError = text.getByText(/HTML character limit exceeded: \d+\/50000/);
    await expect(limitError).toBeVisible();
    await text.getByRole('button', { name: 'Section settings for Text' }).click();
    await expect(body).toHaveCount(0);

    const settingsWrites: string[] = [];
    page.on('request', (request) => {
      if (request.method() !== 'GET' && request.url().includes(`/events/${event.eventId}/settings`)) {
        settingsWrites.push(request.url());
      }
    });

    await designer.saveButton.click();

    await expect(body).toBeFocused();
    await expect(limitError).toBeInViewport();
    await expect(designer.unsavedStatus).toBeVisible();
    expect(settingsWrites).toEqual([]);

    await body.fill('Doors open at seven.');
    await expect(limitError).toHaveCount(0);
    await designer.saveButton.click();
    await expect(designer.savedStatus).toBeVisible();
    expect(settingsWrites).toHaveLength(1);

    const settings = await api.getEventSettings(event.eventId);
    const sections = settings.homepage_sections as { type: string; body?: string }[];
    expect(sections.find((section) => section.type === 'TEXT')?.body).toContain('Doors open at seven.');
  });

  test('the section limit explains why Add section is unavailable', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const builtIns = ['TICKETS', 'ABOUT', 'LINEUP', 'SCHEDULE', 'LOCATION', 'ORGANIZER', 'MORE_EVENTS'];
    await api.updateEventSettings(event.eventId, {
      homepage_sections: [
        ...builtIns.map((type) => ({ id: type.toLowerCase(), type, enabled: true })),
        ...Array.from({ length: 41 - builtIns.length }, (_, index) => ({ id: `text_${index}`, type: 'TEXT', enabled: true, heading: `Text ${index + 1}`, body: '<p>Hello</p>' })),
      ],
    });
    const designer = new DesignerPage(page);
    await designer.gotoHomepageDesigner(event.eventId);
    await expect(designer.sectionRow('text')).toHaveCount(34);

    const addButton = page.getByTestId('homepage-add-section-button');
    await expect(addButton).toHaveAttribute('aria-disabled', 'true');
    await addButton.hover();
    await expect(page.getByRole('tooltip', { name: 'You can add up to 41 sections.' })).toBeVisible();
    await addButton.click({ force: true });
    await expect(page.getByTestId('homepage-add-section-text')).toHaveCount(0);

    await designer.sectionRow('text').last().getByRole('button', { name: 'Section settings for Text 34' }).click();
    await designer.sectionRow('text').last().getByRole('button', { name: 'Remove section' }).click();
    await expect(addButton).not.toHaveAttribute('aria-disabled');
    await addButton.click();
    await expect(page.getByTestId('homepage-add-section-text')).toBeVisible();
  });

  test('the sponsor limit replaces the upload area with a note', async ({ authedPage: page, api, account }) => {
    test.setTimeout(180_000);
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const logos: number[] = [];
    for (let batch = 0; batch < 6; batch++) {
      logos.push(...(await Promise.all(Array.from({ length: 10 }, () => uploadSponsorLogo(page, event.eventId)))));
    }
    await api.updateEventSettings(event.eventId, {
      homepage_sections: [
        { id: 'tickets', type: 'TICKETS', enabled: true },
        { id: 'sponsors', type: 'SPONSORS', enabled: true, sponsors: logos.map((image_id, index) => ({ image_id, name: `Sponsor ${index + 1}` })) },
      ],
    });
    const designer = new DesignerPage(page);
    await designer.gotoHomepageDesigner(event.eventId);

    const sponsors = designer.sectionRow('sponsors');
    await sponsors.getByRole('button', { name: 'Section settings for Sponsors' }).click();
    await expect(sponsors.getByTestId('homepage-sponsors-limit')).toHaveText("You've added the maximum of 60 sponsors.");
    await expect(sponsors.getByText('Upload sponsor logos')).toHaveCount(0);

    await sponsors.getByRole('button', { name: 'Remove sponsor' }).last().click();
    await expect(sponsors.getByTestId('homepage-sponsors-limit')).toHaveCount(0);
    await expect(sponsors.getByText('Upload sponsor logos')).toBeVisible();
  });
});
