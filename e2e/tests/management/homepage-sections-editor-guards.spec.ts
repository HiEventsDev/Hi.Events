import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { DesignerPage } from '../../pages/designer.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { invitedMember } from '../../utils/team';

test.describe('homepage section editor guards', () => {
  test('drag handles are named and keep their instructions after hydration', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const designer = new DesignerPage(page);
    await designer.gotoHomepageDesigner(event.eventId);

    await expect(designer.sectionRow('tickets').getByRole('button', { name: 'Drag to reorder Tickets' })).toBeVisible();
    await expect(designer.sectionRow('about').getByRole('button', { name: 'Drag to reorder About' })).toBeVisible();

    const describedBy = await page.evaluate(() =>
      Array.from(document.querySelectorAll('[data-testid="homepage-sections-list"] [aria-describedby]')).map((element) => {
        const id = element.getAttribute('aria-describedby') ?? '';
        return { id, exists: !!document.getElementById(id) };
      }),
    );
    expect(describedBy.length).toBeGreaterThan(0);
    expect(describedBy.filter((entry) => !entry.exists)).toEqual([]);
  });

  test('server limits are flagged on the field before saving', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const designer = new DesignerPage(page);
    await designer.gotoHomepageDesigner(event.eventId);

    const faq = await designer.addSection('faq');
    await faq.getByTestId('homepage-faq-add').click();
    await faq.getByLabel('Question 1').fill('Is there parking?');
    await faq.getByLabel('Answer 1').fill('a'.repeat(5001));
    await expect(faq.getByText('This answer is too long. Shorten it before saving.')).toBeVisible();
    await faq.getByLabel('Answer 1').fill('Yes, free parking opens two hours before doors.');
    await expect(faq.getByText('This answer is too long. Shorten it before saving.')).toHaveCount(0);

    const video = await designer.addSection('video');
    await video.getByTestId('homepage-video-url').fill('http://www.youtube.com/watch?v=dQw4w9WgXcQ');
    await expect(video.getByTestId('homepage-video-url')).toHaveValue('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    await expect(video).toContainText('YouTube video found');

    await designer.saveButton.click();
    await expect(designer.savedStatus).toBeVisible();

    const settings = await api.getEventSettings(event.eventId);
    const sections = settings.homepage_sections as { type: string; video_url?: string }[];
    expect(sections.find((section) => section.type === 'VIDEO')?.video_url).toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  });

  test('an FAQ without an answer is a plain row, not an expandable one', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.updateEventSettings(event.eventId, {
      homepage_sections: [
        { id: 'tickets', type: 'TICKETS', enabled: true },
        {
          id: 'faqs',
          type: 'FAQ',
          enabled: true,
          faqs: [
            { question: 'Is there parking?', answer: '<p>Yes, free parking opens two hours before doors.</p>' },
            { question: 'Doors open at 7pm', answer: null },
          ],
        },
      ],
    });

    await new PublicEventPage(page).goto(event.eventId, event.slug);

    const faq = page.getByTestId('homepage-section-faq');
    await expect(faq.getByText('Doors open at 7pm')).toBeVisible();
    await expect(faq.locator('details')).toHaveCount(1);
    await expect(faq.locator('details summary')).toHaveText('Is there parking?');
    await expect(faq.locator('details', { hasText: 'Doors open at 7pm' })).toHaveCount(0);
  });

  test('an event-only member is not linked to organizer settings they cannot open', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    await freshAccount.api.updateOrganizerStatus(freshAccount.organizerId, 'DRAFT');

    const ownerPage = await freshAccount.newAuthedPage();
    const ownerDesigner = new DesignerPage(ownerPage);
    await ownerDesigner.gotoHomepageDesigner(event.eventId);
    const ownerOrganizerRow = ownerDesigner.sectionRow('organizer');
    await ownerOrganizerRow.getByRole('button', { name: 'Section settings' }).click();
    await expect(ownerOrganizerRow).toContainText("Your organizer profile isn't published.");
    await expect(ownerOrganizerRow.getByRole('link', { name: 'Organizer settings' })).toBeVisible();

    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      access_scope: 'RESTRICTED',
      event_ids: [event.eventId],
    });
    const designer = new DesignerPage(page);
    await designer.gotoHomepageDesigner(event.eventId);
    const organizerRow = designer.sectionRow('organizer');
    await organizerRow.getByRole('button', { name: 'Section settings' }).click();
    await expect(organizerRow).toContainText("Your organizer profile isn't published.");
    await expect(organizerRow.getByRole('link', { name: 'Organizer settings' })).toHaveCount(0);
  });
});
