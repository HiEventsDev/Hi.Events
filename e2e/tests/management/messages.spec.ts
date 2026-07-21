import { test, expect } from '../../fixtures';
import { MessagePage } from '../../pages/message.page';
import { createDraftEvent } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('messages', () => {
  test('an organizer sends a message to all attendees and sees it in the list', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const subject = uniqueName('Important update');

    const messages = new MessagePage(authedPage);
    await messages.goto(event.eventId);
    await messages.sendToAllAttendees(subject, 'Thanks for registering — see you soon!');

    await expect(authedPage.getByText(subject).first()).toBeVisible();
  });
});
