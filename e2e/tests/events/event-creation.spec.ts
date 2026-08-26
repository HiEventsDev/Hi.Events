import { test, expect } from '../../fixtures';
import { EventCreatePage } from '../../pages/event-create.page';
import { uniqueName } from '../../utils/unique';

test.describe('event creation', () => {
  test('an organizer creates a single event via the dashboard modal', { tag: '@smoke' }, async ({ freshAccount }) => {
    const title = uniqueName('E2E Single Event');
    const page = await freshAccount.newAuthedPage();

    const events = new EventCreatePage(page);
    await events.gotoDashboard();
    await events.openCreateModal();
    await events.createSingleEvent({ title });

    await expect(page).toHaveURL(/\/manage\/event\/\d+\/dashboard/);
    await expect(page.getByText(title).first()).toBeVisible();
  });
});
