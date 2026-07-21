import { test, expect } from '../../fixtures';
import { EventCreatePage } from '../../pages/event-create.page';
import { uniqueName } from '../../utils/unique';

test.describe('event creation', () => {
  test('an organizer creates a single event via the dashboard modal', { tag: '@smoke' }, async ({ authedPage }) => {
    const title = uniqueName('E2E Single Event');

    const events = new EventCreatePage(authedPage);
    await events.gotoDashboard();
    await events.openCreateModal();
    await events.createSingleEvent({ title });

    await expect(authedPage).toHaveURL(/\/manage\/event\/\d+\/dashboard/);
    await expect(authedPage.getByText(title).first()).toBeVisible();
  });
});
