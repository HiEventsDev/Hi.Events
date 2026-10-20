import { test, expect } from '../../fixtures';
import { bootstrapAccount } from '../../fixtures/account.fixture';
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

  test('someone who already has an account signs in from the invitation and joins the team', async ({ freshAccount, page, mailpit, publicApi }) => {
    const existing = await bootstrapAccount(publicApi, mailpit);
    const { name: accountName } = await freshAccount.api.getAccount();
    await freshAccount.api.inviteUser({ first_name: 'Jordan', last_name: 'Existing', email: existing.email, role: 'ORGANIZER' });
    const inviteUrl = await mailpit.waitForLink(existing.email, /accept-invitation/, { subjectContains: 'invited to join' });

    await page.goto(inviteUrl.pathname);
    await expect(page.getByRole('heading', { name: `Join ${accountName}` })).toBeVisible();
    await expect(page.getByText('You already have an account.')).toBeVisible();
    await page.getByRole('button', { name: 'Sign in with password' }).click();

    await expect(page).toHaveURL(/\/auth\/login/);
    await expect(page.getByLabel(/^Email/)).toHaveValue(existing.email);
    await page.getByLabel(/^Password/).fill(existing.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();

    await expect(page).toHaveURL(new RegExp(inviteUrl.pathname));
    await page.getByTestId('invitation-join-button').click();

    await expect(page).toHaveURL(new RegExp(`/manage/organizer/${freshAccount.organizerId}`));
    await expect(page.getByRole('heading', { level: 1, name: /Dashboard/ })).toBeVisible();

    const ownerPage = await freshAccount.newAuthedPage();
    const team = new TeamPage(ownerPage);
    await team.goto();
    await expect(team.userRow(existing.email)).toBeVisible();
    await expect(team.userRow(existing.email).getByText(/^Invited$/)).toHaveCount(0);
  });

  test('opening an invitation while signed in as someone else asks you to sign out first', async ({ freshAccount, mailpit, publicApi }) => {
    const existing = await bootstrapAccount(publicApi, mailpit);
    await freshAccount.api.inviteUser({ first_name: 'Riley', last_name: 'Existing', email: existing.email, role: 'ORGANIZER' });
    const inviteUrl = await mailpit.waitForLink(existing.email, /accept-invitation/, { subjectContains: 'invited to join' });

    const ownerPage = await freshAccount.newAuthedPage();
    await ownerPage.goto(inviteUrl.pathname);
    await expect(ownerPage.getByText(`You're signed in as ${freshAccount.email}`)).toBeVisible();
    await expect(ownerPage.getByTestId('invitation-join-button')).toHaveCount(0);

    await ownerPage.getByTestId('invitation-sign-out-button').click();

    await expect(ownerPage.getByText('You already have an account.')).toBeVisible();
    await expect(ownerPage.getByRole('button', { name: 'Sign in with password' })).toBeVisible();
  });
});
