import { test, expect } from '../../fixtures';
import { OrganizerPublicPage } from '../../pages/organizer.page';
import { createFreshOrganizer, createLiveEventWithProduct } from '../../api/factory';
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

    const summary = await mailpit.waitForMessage(organizerEmail, { subjectContains: senderName });
    expect(summary.Subject).toBe(`New message from your organizer page`);
    const message = await mailpit.getMessage(summary.ID);
    expect(`${message.Text}\n${message.HTML}`).toContain(reference);
  });
});
