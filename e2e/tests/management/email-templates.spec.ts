import { test, expect } from '../../fixtures';
import { EmailTemplatePage } from '../../pages/email-template.page';
import { createDraftEvent, createFreshOrganizer } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('email templates', () => {

  test('an organizer creates an event-level order confirmation template', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const subject = uniqueName('Event Subject');
    const body = uniqueName('Event body copy');

    const templates = new EmailTemplatePage(authedPage);
    await templates.gotoEventTemplates(event.eventId);
    await templates.openCreateEditor('Order Confirmation');

    await expect(templates.subjectInput()).not.toHaveValue('');
    await templates.fillEditor(subject, body);
    await templates.saveTemplate();

    const card = templates.templateCard('Order Confirmation');
    await expect(card.getByText(subject)).toBeVisible();
    await expect(card.getByText('Active', { exact: true })).toBeVisible();
  });

  test('the template preview renders the saved subject and body', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const subject = uniqueName('Preview Subject');
    const bodyText = uniqueName('Preview body copy');
    await api.createEventEmailTemplate(event.eventId, {
      template_type: 'order_confirmation',
      subject,
      body: `<p>${bodyText}</p>`,
      ctaLabel: 'View Order',
    });

    const templates = new EmailTemplatePage(authedPage);
    await templates.gotoEventTemplates(event.eventId);
    await templates.openEditEditor('Order Confirmation');

    await expect(templates.subjectInput()).toHaveValue(subject);
    await templates.openPreviewTab();

    await expect(templates.editorDialog().getByText(subject, { exact: true })).toBeVisible();
    await expect(templates.editorDialog().getByText(bodyText)).toBeVisible();
  });

  test('an organizer deletes an event-level template and the card resets', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const subject = uniqueName('Doomed Subject');
    await api.createEventEmailTemplate(event.eventId, {
      template_type: 'order_confirmation',
      subject,
      body: '<p>To be deleted</p>',
      ctaLabel: 'View Order',
    });

    const templates = new EmailTemplatePage(authedPage);
    await templates.gotoEventTemplates(event.eventId);

    const card = templates.templateCard('Order Confirmation');
    await expect(card.getByText(subject)).toBeVisible();

    await templates.deleteTemplate('Order Confirmation');

    await expect(card.getByTestId('email-template-create-button')).toBeVisible();
    await expect(card.getByText(subject)).toHaveCount(0);
  });

  test('an organizer creates an organizer-level attendee ticket template', async ({ authedPage, api }) => {
    const organizer = await createFreshOrganizer(api);
    const subject = uniqueName('Org Subject');
    const body = uniqueName('Org body copy');

    const templates = new EmailTemplatePage(authedPage);
    await templates.gotoOrganizerTemplates(organizer.id);
    await templates.openCreateEditor('Attendee Ticket');

    await expect(templates.subjectInput()).not.toHaveValue('');
    await templates.fillEditor(subject, body);
    await templates.saveTemplate();

    const card = templates.templateCard('Attendee Ticket');
    await expect(card.getByText(subject)).toBeVisible();
    await expect(card.getByText('Active', { exact: true })).toBeVisible();
  });
});
