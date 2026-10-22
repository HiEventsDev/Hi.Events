import { test, expect } from '../../fixtures';
import { createLiveEventWithProduct } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { PublicEventPage } from '../../pages/public-event.page';

const travelInfo = Array.from({ length: 18 }, (_, index) => `<p>Travel note ${index + 1}: the shuttle leaves the square every twenty minutes.</p>`).join('');

const createEventWithSections = async (api: ApiClient, organizerId: number) => {
  const event = await createLiveEventWithProduct(api, { organizerId, price: 15 });
  await api.updateEventSettings(event.eventId, {
    homepage_theme_settings: { accent: '#7c3aed', background: '#f5f3ff', mode: 'light', background_type: 'COLOR', layout: 'MARQUEE' },
    homepage_sections: [
      { id: 'tickets', type: 'TICKETS', enabled: true },
      { id: 'travel', type: 'TEXT', enabled: true, heading: 'Getting there', body: travelInfo },
      { id: 'faq', type: 'FAQ', enabled: true, heading: 'Questions', faqs: [{ question: 'Is there parking?', answer: '<p>Free parking opens two hours before doors.</p>' }] },
    ],
  });
  return event;
};

test.describe('event page navigation', () => {
  test('on a desktop the tickets sit beside the title and the section bar takes the visitor to a section', async ({ page, api, account }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    const event = await createEventWithSections(api, account.organizerId);
    const publicPage = new PublicEventPage(page);
    await publicPage.goto(event.eventId, event.slug);

    const titleBox = (await page.getByRole('heading', { level: 1, name: event.title }).boundingBox())!;
    const ticketsBox = (await publicPage.ticketsRegion().boundingBox())!;
    expect(ticketsBox.x).toBeGreaterThan(titleBox.x + titleBox.width);
    expect(ticketsBox.y).toBeLessThan(titleBox.y + titleBox.height);

    await expect(publicPage.sectionBar()).toBeHidden();
    await page.mouse.wheel(0, 900);
    await expect(publicPage.sectionBar()).toBeVisible();

    await publicPage.sectionBar().getByRole('link', { name: 'Questions' }).click();
    const faq = page.getByTestId('homepage-section-faq');
    await expect(faq).toBeInViewport();
    await expect(faq).toBeFocused();
    await expect(publicPage.ticketsRegion()).toBeInViewport();
  });

  test('on a phone the tickets follow the event details and the bottom bar brings the buyer back to them', async ({ page, api, account }) => {
    await page.setViewportSize({ width: 390, height: 740 });
    const event = await createEventWithSections(api, account.organizerId);
    const publicPage = new PublicEventPage(page);
    await publicPage.goto(event.eventId, event.slug);

    const titleBox = (await page.getByRole('heading', { level: 1, name: event.title }).boundingBox())!;
    const ticketsBox = (await publicPage.ticketsRegion().boundingBox())!;
    expect(ticketsBox.y).toBeGreaterThan(titleBox.y + titleBox.height);
    await expect(publicPage.sectionBar()).toBeHidden();

    await page.evaluate(() => window.scrollTo(0, document.getElementById('tickets')!.getBoundingClientRect().bottom + window.scrollY + 40));
    const getTickets = page.getByRole('button', { name: 'Get Tickets' });
    await expect(getTickets).toBeVisible();
    await getTickets.click();

    await expect(publicPage.ticketsRegion()).toBeInViewport();
    await expect(publicPage.ticketsRegion()).toBeFocused();
  });
});
