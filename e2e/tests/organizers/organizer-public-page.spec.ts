import { test, expect } from '../../fixtures';
import { OrganizerPublicPage } from '../../pages/organizer.page';
import { createFreshOrganizer, createLiveEventWithProduct, createRecurringLiveEvent } from '../../api/factory';
import { uniqueCode, uniqueEmail, uniqueName, uniqueShort } from '../../utils/unique';

test.describe('organizer public page', () => {
  test("a visitor sees the organizer's upcoming event and clicks through to it", async ({ page, api }) => {
    const organizer = await createFreshOrganizer(api, uniqueName('E2E Public Org'));
    await api.updateOrganizerStatus(organizer.id, 'LIVE');
    const event = await createLiveEventWithProduct(api, { organizerId: organizer.id });

    const publicPage = new OrganizerPublicPage(page);
    await publicPage.goto(organizer.id, organizer.slug);

    await expect(page.getByRole('heading', { name: organizer.name })).toBeVisible();
    await publicPage.eventLink(event.title).click();

    await expect(page.getByRole('heading', { name: event.title })).toBeVisible();
  });

  test('a recurring event card shows its next date, remaining dates and only publicly visible prices', async ({ page, api }) => {
    const organizer = await createFreshOrganizer(api, uniqueName('E2E Listing Org'));
    await api.updateOrganizerStatus(organizer.id, 'LIVE');
    const event = await createRecurringLiveEvent(api, organizer.id, { count: 3, price: 25 });
    const categories = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'Hidden Staff Ticket',
      product_type: 'TICKET',
      type: 'PAID',
      product_category_id: categories[0].id,
      is_hidden: true,
      prices: [{ price: 1 }],
    });
    const [next] = [...event.occurrences].sort((a, b) => a.start_date.localeCompare(b.start_date));
    const nextDate = new Date(next.start_date);
    const nextLabel = `${nextDate.toLocaleString('en-US', { month: 'short', timeZone: 'UTC' })} ${nextDate.getUTCDate()}`;

    const publicPage = new OrganizerPublicPage(page);
    await publicPage.goto(organizer.id, organizer.slug);

    const card = publicPage.eventLink(event.title);
    await expect(card.getByTestId('organizer-event-card-date')).toContainText(nextLabel);
    await expect(card.getByTestId('organizer-event-card-date')).toContainText(/7:00\s?PM/);
    await expect(card.getByText('+2 more dates')).toBeVisible();
    await expect(card.getByText('Happening now')).toHaveCount(0);
    const price = card.getByTestId('organizer-event-card-price');
    await expect(price).toContainText(/\$\d/);
    await expect(price).not.toContainText('From');

    await page.getByRole('button', { name: 'Past', exact: true }).click();
    await expect(page.getByText('No past events')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Events', exact: true })).toBeInViewport();
  });

  test('an event whose only ticket is sold out and hidden still shows as sold out', async ({ page, api }) => {
    const organizer = await createFreshOrganizer(api, uniqueName('E2E Sold Out Org'));
    await api.updateOrganizerStatus(organizer.id, 'LIVE');
    const event = await createLiveEventWithProduct(api, {
      organizerId: organizer.id,
      price: 20,
      quantityAvailable: 0,
      hideWhenSoldOut: true,
    });

    const publicPage = new OrganizerPublicPage(page);
    await publicPage.goto(organizer.id, organizer.slug);

    await expect(publicPage.eventLink(event.title).getByTestId('organizer-event-card-price')).toHaveText('Sold out');
  });

  test('a visitor contacts the organizer and the message arrives by email', async ({ page, api, mailpit }) => {
    const organizerEmail = uniqueEmail('organizer');
    const organizer = await api.createOrganizer(uniqueName('E2E Contact Org'), { email: organizerEmail });
    await api.updateOrganizerStatus(organizer.id, 'LIVE');

    const senderName = uniqueShort('Visitor');
    const reference = uniqueCode('REF');

    const publicPage = new OrganizerPublicPage(page);
    await publicPage.goto(organizer.id, organizer.slug);
    await publicPage.contactButton.click();
    await publicPage.sendContactMessage({
      name: senderName,
      email: uniqueEmail('visitor'),
      message: `Accessibility question ${reference}`,
    });

    await expect(page.getByText('Your message has been sent successfully!')).toBeVisible();

    const summary = await mailpit.waitForMessage(organizerEmail, { subjectContains: 'New message from your organizer page' });
    expect(summary.Subject).toBe('New message from your organizer page');
    const message = await mailpit.getMessage(summary.ID);
    expect(`${message.Text}\n${message.HTML}`).toContain(reference);
  });
});
