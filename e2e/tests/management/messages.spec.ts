import type { Page, Request } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { MessagePage } from '../../pages/message.page';
import { createDraftEvent, createDraftEventWithTicket } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

const isSendMessageRequest = (request: Request): boolean =>
  request.method() === 'POST' && /\/events\/\d+\/messages$/.test(new URL(request.url()).pathname);

const fillSubjectAndBody = async (page: Page, subject: string, body: string): Promise<void> => {
  await page.getByLabel(/^Subject/).fill(subject);
  const editor = page.locator('.ProseMirror').first();
  await editor.click();
  await editor.fill(body);
};

const confirmTransactional = (page: Page) =>
  page.getByRole('checkbox', { name: /I confirm this is a transactional message/ }).check();

test.describe('messages', () => {

  test('an organizer sends a message to all attendees and sees it in the list', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const subject = uniqueName('Important update');

    const messages = new MessagePage(authedPage);
    await messages.goto(event.eventId);
    await messages.sendToAllAttendees(subject, 'Thanks for registering — see you soon!');

    await expect(authedPage.getByText(subject).first()).toBeVisible();
  });

  test('composing without choosing recipients shows an error under Recipients and sends nothing', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const sendRequests: Request[] = [];
    authedPage.on('request', (request) => {
      if (isSendMessageRequest(request)) {
        sendRequests.push(request);
      }
    });

    const messages = new MessagePage(authedPage);
    await messages.goto(event.eventId);
    await authedPage.getByTestId('message-compose-button').click();
    await authedPage.getByRole('heading', { name: 'Send a message' }).waitFor();

    await fillSubjectAndBody(authedPage, uniqueName('No recipients'), 'Who is this for?');
    await confirmTransactional(authedPage);
    await authedPage.getByTestId('message-send-button').click();

    await expect(authedPage.getByText('Choose who should receive this message')).toBeVisible();
    await expect(authedPage.getByRole('heading', { name: 'Send a message' })).toBeVisible();
    expect(sendRequests).toHaveLength(0);
  });

  test("messaging attendees from a ticket's menu keeps that ticket selected and sends", async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId, { productTitle: uniqueName('Early Bird') });

    await authedPage.goto(`/manage/event/${event.eventId}/products`);
    await authedPage.waitForLoadState('networkidle');
    await authedPage.getByTestId('product-manage-button').first().click();
    await authedPage.getByRole('menuitem', { name: 'Message Attendees' }).click();
    await authedPage.getByRole('heading', { name: 'Send a message' }).waitFor();

    await expect(authedPage.locator('.mantine-MultiSelect-pill').filter({ hasText: event.productTitle })).toBeVisible();

    await fillSubjectAndBody(authedPage, uniqueName('Ticket holders'), 'A note for this ticket.');
    await confirmTransactional(authedPage);

    const sent = authedPage.waitForResponse((response) => isSendMessageRequest(response.request()));
    await authedPage.getByTestId('message-send-button').click();
    const response = await sent;

    expect(response.ok()).toBe(true);
    const body = response.request().postDataJSON();
    expect(body.message_type).toBe('TICKET_HOLDERS');
    expect(body.product_ids.map(Number)).toEqual([event.productId]);
  });

  test('a test sent while Schedule for later is selected goes to the sender now and schedules nothing', async ({ authedPage, api, account, mailpit }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const subject = uniqueName('Draft reminder');

    const messages = new MessagePage(authedPage);
    await messages.goto(event.eventId);
    await authedPage.getByTestId('message-compose-button').click();
    await authedPage.getByRole('heading', { name: 'Send a message' }).waitFor();
    await authedPage.getByRole('combobox', { name: 'Recipients' }).click();
    await authedPage.getByRole('option', { name: 'All attendees of this event' }).click();
    await fillSubjectAndBody(authedPage, subject, 'Checking how this looks.');

    await authedPage.getByRole('button', { name: 'Schedule for later' }).click();
    await authedPage.getByRole('button', { name: '1 day before event' }).click();
    await confirmTransactional(authedPage);
    await expect(authedPage.getByTestId('message-send-button')).toHaveText('Schedule Message');

    await authedPage.getByTestId('message-send-options-button').click();
    await authedPage.getByRole('menuitem', { name: 'Send as test' }).click();
    await expect(authedPage.getByTestId('message-send-button')).toHaveText('Send Test');

    const sent = authedPage.waitForResponse((response) => isSendMessageRequest(response.request()));
    await authedPage.getByTestId('message-send-button').click();
    const response = await sent;

    expect(response.ok()).toBe(true);
    const body = response.request().postDataJSON();
    expect(body.is_test).toBe(true);
    expect(body.scheduled_at).toBeUndefined();
    expect((await response.json()).data.scheduled_at).toBeNull();

    await expect(authedPage.getByText('Test email sent to your inbox')).toBeVisible();
    await expect(messages.listItem(subject)).toBeVisible();
    await expect(messages.listItem(subject).getByText('SCHEDULED', { exact: true })).toHaveCount(0);
    await mailpit.waitForMessage(account.email, { subjectContains: subject });
  });
});
