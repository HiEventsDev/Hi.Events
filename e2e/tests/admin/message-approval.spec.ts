import { test, expect } from '../../fixtures';
import { MessagePage } from '../../pages/message.page';
import { createEventWithAttendee } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('admin message approval', () => {
  test('an untrusted account message is held for review, approved by an admin, then delivered', { tag: '@admin' }, async ({ freshAccount, adminApi, superAdminPage, mailpit }) => {
    const { id: accountId } = await freshAccount.api.getAccount();
    await adminApi.setMessagingTier(accountId, 1);
    const event = await createEventWithAttendee(freshAccount.api, freshAccount.organizerId);
    const subject = uniqueName('Venue update');

    const organizerPage = await freshAccount.newAuthedPage();
    const messages = new MessagePage(organizerPage);
    await messages.goto(event.eventId);
    await messages.sendToAllAttendees(subject, 'The venue has changed, please check your tickets.');

    await expect(messages.listItem(subject)).toBeVisible();
    await expect(messages.listItem(subject).getByText(/^PENDING_REVIEW$/)).toBeVisible();

    await superAdminPage.goto('/admin/messages');
    await superAdminPage.waitForLoadState('networkidle');
    await superAdminPage.getByPlaceholder(/^Search by subject/).fill(subject);

    const messageRow = superAdminPage.getByRole('row').filter({ hasText: subject });
    await expect(messageRow).toBeVisible();
    await messageRow.getByTestId('admin-approve-message-button').click();
    await expect(messageRow.getByText(/^SENT$/)).toBeVisible();

    const delivered = await mailpit.waitForMessage(event.attendeeEmail, { subjectContains: subject });
    expect(delivered.Subject).toContain(subject);

    await organizerPage.reload();
    await organizerPage.waitForLoadState('networkidle');
    await expect(messages.listItem(subject).getByText(/^SENT$/)).toBeVisible();
  });
});
