import { test, expect } from '../../fixtures';
import { TeamPage } from '../../pages/team.page';
import { uniqueEmail } from '../../utils/unique';

test.describe('edit team member', () => {
  test('a member without a last name can be made a viewer', async ({ freshAccount }) => {
    const email = uniqueEmail('nolast');
    await freshAccount.api.inviteUser({ first_name: 'Solo', email, role: 'ORGANIZER' });

    const page = await freshAccount.newAuthedPage();
    const team = new TeamPage(page);
    await team.goto();
    for (const label of ['Role', 'Access', 'Two-factor', 'Last active']) {
      await expect(team.userRow(email)).toContainText(label);
    }
    await team.openEditUserModal(email);

    const dialog = page.getByRole('dialog');
    await expect(dialog.getByRole('radiogroup', { name: 'Role' })).toBeVisible();
    await expect(dialog.getByRole('radiogroup', { name: 'Access' })).toBeVisible();
    await expect(dialog.getByRole('switch', { name: 'Can see financials' }))
      .toHaveAccessibleDescription('Revenue, order totals, reports, payouts and payment settings.');

    await team.selectRole('Viewer');
    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'PUT' && /\/api\/users\/\d+$/.test(res.url())),
      team.submitEditUser(),
    ]);

    expect(response.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Edit member' })).toHaveCount(0);
    await expect(team.userRow(email)).toContainText('Viewer');
  });
});
