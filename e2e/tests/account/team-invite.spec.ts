import { test, expect } from '../../fixtures';
import { TeamPage } from '../../pages/team.page';
import { uniqueEmail } from '../../utils/unique';

const INVITEE_PASSWORD = 'InviteePass123!';

test.describe('team invites', () => {
  test('an invited team member accepts the invitation and logs in', { tag: '@smoke' }, async ({ freshAccount, page, mailpit }) => {
    const inviteeEmail = uniqueEmail('invitee');

    const ownerPage = await freshAccount.newAuthedPage();
    const team = new TeamPage(ownerPage);
    await team.goto();
    await team.inviteUser({ firstName: 'Robin', lastName: 'Member', email: inviteeEmail, role: 'Organizer' });

    await expect(team.userRow(inviteeEmail)).toBeVisible();
    await expect(team.userRow(inviteeEmail).getByText(/^Invited$/)).toBeVisible();

    const inviteUrl = await mailpit.waitForLink(inviteeEmail, /accept-invitation/, { subjectContains: 'invited to join' });
    await page.goto(inviteUrl.pathname);
    await expect(page.getByRole('heading', { name: /^Join / })).toBeVisible();
    await expect(page.getByLabel(/^First Name/)).toHaveValue('Robin');

    await page.getByLabel(/^Password/).fill(INVITEE_PASSWORD);
    await page.getByLabel(/^Confirm Password/).fill(INVITEE_PASSWORD);
    await page.getByRole('checkbox', { name: /I agree/ }).check();
    await page.getByTestId('invitation-accept-button').click();

    await expect(page).toHaveURL(/\/auth\/login/);
    await page.getByLabel(/^Email/).fill(inviteeEmail);
    await page.getByLabel(/^Password/).fill(INVITEE_PASSWORD);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();

    await expect(page).toHaveURL(/\/manage\/organizer\/\d+/);
    await expect(page.getByRole('heading', { level: 1, name: /Dashboard/ })).toBeVisible();
  });

  test('an owner promotes a member to admin and deactivates them', async ({ freshAccount, page, mailpit, publicApi }) => {
    const memberEmail = uniqueEmail('member');

    await freshAccount.api.inviteUser({ first_name: 'Casey', last_name: 'Member', email: memberEmail, role: 'ORGANIZER' });
    const inviteUrl = await mailpit.waitForLink(memberEmail, /accept-invitation/, { subjectContains: 'invited to join' });
    const inviteToken = inviteUrl.pathname.split('/').pop();
    const acceptResponse = await publicApi.post(`auth/invitation/${inviteToken}`, {
      data: {
        first_name: 'Casey',
        last_name: 'Member',
        password: INVITEE_PASSWORD,
        password_confirmation: INVITEE_PASSWORD,
        timezone: 'UTC',
      },
    });
    expect(acceptResponse.ok()).toBeTruthy();

    const ownerPage = await freshAccount.newAuthedPage();
    const team = new TeamPage(ownerPage);
    await team.goto();
    await expect(team.userRow(memberEmail)).toBeVisible();
    await expect(team.userRow(memberEmail).getByText(/^(Invited|Inactive)$/)).toHaveCount(0);

    await team.openEditUserModal(memberEmail);
    await team.selectRole('Admin');
    await team.submitEditUser();
    await expect(team.userRow(memberEmail).getByText(/^Admin$/)).toBeVisible();

    await team.deactivate(memberEmail);
    await expect(team.userRow(memberEmail).getByText(/^Inactive$/)).toBeVisible();

    await page.goto('/auth/login');
    await page.waitForLoadState('networkidle');
    await page.getByLabel(/^Email/).fill(memberEmail);
    await page.getByLabel(/^Password/).fill(INVITEE_PASSWORD);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page.getByText('Please check your email and password and try again')).toBeVisible();
  });
});
