import { fileURLToPath } from 'node:url';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { DesignerPage } from '../../pages/designer.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { uniqueName } from '../../utils/unique';

const GALLERY_PHOTO = fileURLToPath(new URL('../../fixtures/assets/gallery-photo.png', import.meta.url));

test.describe('homepage sections', () => {
  test('an organizer hides, adds and reorders homepage sections', { tag: '@smoke' }, async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const performer = uniqueName('Headliner');
    const profile = await api.createLineupProfile(account.organizerId, { name: performer });
    await api.addLineupEntry(event.eventId, { profile_id: profile.id, billing_tier: 'HEADLINER' });

    const designer = new DesignerPage(page);
    await designer.gotoHomepageDesigner(event.eventId);
    await expect(page.getByTestId('homepage-section-empty-schedule')).toBeVisible();
    await expect(page.getByTestId('homepage-section-empty-lineup')).toHaveCount(0);
    await designer.toggleSection('lineup');

    const text = await designer.addSection('text');
    await expect(page.getByTestId('homepage-section-empty-text')).toBeVisible();
    await text.getByLabel('Heading').fill('Getting there');
    await text.locator('[contenteditable="true"]').fill('Shuttle buses leave from the town square every 20 minutes.');
    await expect(page.getByTestId('homepage-section-empty-text')).toHaveCount(0);

    const faq = await designer.addSection('faq');
    await faq.getByTestId('homepage-faq-add').click();
    await faq.getByLabel('Question 1').fill('Is there parking?');
    await faq.getByLabel('Answer 1').pressSequentially('Yes, free parking opens two hours before doors.');
    await expect(faq.getByLabel('Answer 1')).toHaveValue('Yes, free parking opens two hours before doors.');

    const video = await designer.addSection('video');
    await video.getByTestId('homepage-video-url').fill('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    await expect(video).toContainText('YouTube video found');

    const gallery = await designer.addSection('gallery');
    await gallery.locator('input[type="file"]').setInputFiles(GALLERY_PHOTO);
    await expect(gallery.locator('img')).toHaveCount(1);

    await designer.moveSectionUp('faq');

    await designer.saveButton.click();
    await expect(designer.savedStatus).toBeVisible();

    await new PublicEventPage(page).goto(event.eventId, event.slug);

    await expect(page.getByTestId('event-lineup-section')).toHaveCount(0);
    await expect(page.getByTestId('homepage-section-text')).toContainText('Getting there');
    await expect(page.getByTestId('homepage-section-text')).toContainText('Shuttle buses leave from the town square');

    const headings = await page.locator('h2').allTextContents();
    expect(headings.indexOf('Questions')).toBeLessThan(headings.indexOf('Getting there'));

    await page.getByTestId('homepage-section-faq').getByText('Is there parking?').click();
    await expect(page.getByTestId('homepage-section-faq')).toContainText('free parking opens two hours before doors');

    await expect(page.getByTestId('homepage-section-video')).toContainText('Plays from YouTube');

    await page.getByTestId('homepage-section-gallery').getByRole('button', { name: 'Open photo 1' }).click();
    await expect(page.getByRole('button', { name: 'Close' })).toBeVisible();
  });
  test('ticket titles come from product categories, not a section heading', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const designer = new DesignerPage(page);
    await designer.gotoHomepageDesigner(event.eventId);

    const tickets = designer.sectionRow('tickets');
    await tickets.getByRole('button', { name: 'Section settings' }).click();
    await expect(tickets.getByLabel('Heading')).toHaveCount(0);
    await expect(tickets.getByTestId('homepage-section-tickets-note')).toContainText('Ticket titles come from your product categories.');

    await tickets.getByRole('link', { name: 'Edit in Tickets & Products' }).click();
    await expect(page).toHaveURL(new RegExp(`/manage/event/${event.eventId}/products`));
  });
});
