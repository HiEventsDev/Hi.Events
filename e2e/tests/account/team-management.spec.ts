import { test, expect } from '../../fixtures';
import { TeamPage } from '../../pages/team.page';
import { invitedMember } from '../../utils/team';
import { uniqueEmail } from '../../utils/unique';

test.describe('team management', () => {
  test('an owner finds members, revokes an invitation and reactivates a member', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const member = await invitedMember(freshAccount, mailpit, publicApi, browser, { last_name: 'Member', role: 'ORGANIZER' });
    const pendingEmail = uniqueEmail('pending');
    await freshAccount.api.inviteUser({ first_name: 'Pat', last_name: 'Pending', email: pendingEmail, role: 'VIEWER' });

    const ownerPage = await freshAccount.newAuthedPage();
    const team = new TeamPage(ownerPage);
    await team.goto();
    await expect(team.userRow(member.email)).toBeVisible();
    await expect(team.userRow(pendingEmail)).toBeVisible();

    await team.search(member.email);
    await expect(team.userRow(member.email)).toBeVisible();
    await expect(team.userRow(pendingEmail)).toHaveCount(0);
    await team.search('');

    await team.chooseRowAction(pendingEmail, 'Revoke invitation');
    await team.confirm();
    await expect(team.userRow(pendingEmail)).toHaveCount(0);

    await team.deactivate(member.email);
    await expect(team.userRow(member.email).getByText(/^Inactive$/)).toBeVisible();

    await team.chooseRowAction(member.email, 'Reactivate');
    await expect(team.userRow(member.email).getByText(/^Inactive$/)).toHaveCount(0);

    await member.page.goto('/manage/events');
    await expect(member.page).toHaveURL(/\/manage\/organizer\/\d+/);
    await expect(member.page.getByRole('heading', { level: 1, name: /Dashboard/ })).toBeVisible();
  });
});
