import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { TeamPage } from '../../pages/team.page';
import { uniqueEmail } from '../../utils/unique';
import { invitedMember } from '../../utils/team';

async function memberPage(...args: Parameters<typeof invitedMember>): Promise<Page> {
  return (await invitedMember(...args)).page;
}

test.describe('team access', () => {
  test('a member invited to one event only sees that event', { tag: '@smoke' }, async ({ freshAccount, mailpit, publicApi, browser }) => {
    const granted = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const hidden = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const grantedTitle = (await freshAccount.api.getEvent(granted.eventId)).title;
    const hiddenTitle = (await freshAccount.api.getEvent(hidden.eventId)).title;

    const ownerPage = await freshAccount.newAuthedPage();
    const team = new TeamPage(ownerPage);
    await team.goto();
    const email = uniqueEmail('scoped');
    await team.inviteUser({ firstName: 'Scoped', lastName: 'Member', email, role: 'Organizer', onlyAccessTo: [grantedTitle] });
    await expect(team.userRow(email).getByText('1 event', { exact: true })).toBeVisible();

    const page = await memberPage(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      access_scope: 'RESTRICTED',
      event_ids: [granted.eventId],
    });

    await page.goto('/manage/events');
    await expect(page.getByText(grantedTitle)).toBeVisible();
    await expect(page.getByText(hiddenTitle)).toHaveCount(0);

    await page.goto(`/manage/event/${hidden.eventId}/dashboard`);
    await expect(page.getByRole('heading', { name: "You don't have access to this event" })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Go to your events' })).toHaveAttribute('href', '/manage/events');
    await expect(page).not.toHaveURL(/\/auth\/login/);
  });

  test('a viewer without financials sees orders but no money and cannot edit', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const order = await createCompletedOrder(publicApi, event);

    const page = await memberPage(freshAccount, mailpit, publicApi, browser, { role: 'VIEWER' });

    await page.goto(`/manage/event/${event.eventId}/orders`);
    await expect(page.getByTestId('view-only-badge')).toBeVisible();
    await expect(page.getByText(order.buyerEmail)).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Amount' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Export' })).toHaveCount(0);

    await page.goto(`/manage/event/${event.eventId}/dashboard`);
    await expect(page.getByTestId('event-stat-gross_sales')).toHaveCount(0);
    await expect(page.getByTestId('event-stat-attendees')).toBeVisible();
  });

  test('an admin removes a member from an event on the team tab', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const first = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const second = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    await memberPage(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      access_scope: 'RESTRICTED',
      event_ids: [first.eventId, second.eventId],
    });

    const ownerPage = await freshAccount.newAuthedPage();
    await ownerPage.goto(`/manage/event/${first.eventId}/team`);
    const table = ownerPage.getByTestId('team-access-table');
    const memberRow = table.getByRole('row').filter({ hasText: 'Sam Member' });
    await expect(memberRow.getByText('This event only')).toBeVisible();

    await memberRow.getByTestId('team-remove-access-button').click();
    await ownerPage.getByRole('button', { name: 'Remove access' }).click();
    await expect(ownerPage.getByText('Access removed')).toBeVisible();
    await expect(memberRow).toHaveCount(0);
  });

  test('a member with an organizer grant manages that organizer only', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const otherOrganizer = await freshAccount.api.createOrganizer('Other Organizer');
    const ownEvent = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const otherEvent = await createLiveEventWithFreeTicket(freshAccount.api, otherOrganizer.id);
    const otherTitle = (await freshAccount.api.getEvent(otherEvent.eventId)).title;

    const page = await memberPage(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      access_scope: 'RESTRICTED',
      organizer_ids: [freshAccount.organizerId],
    });

    await page.goto('/manage/events');
    await expect(page).toHaveURL(new RegExp(`/manage/organizer/${freshAccount.organizerId}`));

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/events`);
    await expect(page.getByText((await freshAccount.api.getEvent(ownEvent.eventId)).title)).toBeVisible();
    await expect(page.getByText(otherTitle)).toHaveCount(0);

    await page.goto(`/manage/organizer/${otherOrganizer.id}/dashboard`);
    await expect(page.getByRole('heading', { name: "You don't have access to this organizer" })).toBeVisible();
    await expect(page.getByText('Other Organizer')).toHaveCount(0);
  });

  test('turning off financials for a member hides order amounts from them', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    await createCompletedOrder(publicApi, event);
    const member = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'ORGANIZER' });

    await member.page.goto(`/manage/event/${event.eventId}/orders`);
    await expect(member.page.getByRole('columnheader', { name: 'Amount' })).toBeVisible();

    const ownerPage = await freshAccount.newAuthedPage();
    const team = new TeamPage(ownerPage);
    await team.goto();
    await team.openEditUserModal(member.email);
    await ownerPage.getByRole('switch', { name: 'Can see financials' }).click({ force: true });
    await team.submitEditUser();
    await expect(ownerPage.getByText('Changes saved')).toBeVisible();

    await member.page.reload();
    await expect(member.page.getByText('Test Buyer')).toBeVisible();
    await expect(member.page.getByRole('columnheader', { name: 'Amount' })).toHaveCount(0);
  });

  test('a member who opts in is emailed about new orders', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const member = await invitedMember(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      access_scope: 'RESTRICTED',
      event_ids: [event.eventId],
    });

    await member.page.goto('/settings/profile');
    await member.page.getByTestId('profile-notify-new-orders').check({ force: true });
    await member.page.getByTestId('profile-save-button').click();
    await expect(member.page.getByTestId('profile-notify-new-orders')).toBeChecked();

    await createCompletedOrder(publicApi, event);
    await mailpit.waitForMessage(member.email, { subjectContains: 'New order for' });
  });

  test('an admin invites someone straight from the event team tab', async ({ freshAccount }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const email = uniqueEmail('teamtab');

    const ownerPage = await freshAccount.newAuthedPage();
    await ownerPage.goto(`/manage/event/${event.eventId}/team`);
    await ownerPage.getByTestId('team-invite-to-scope-button').click();
    await ownerPage.getByRole('heading', { name: 'Invite a team member' }).waitFor();
    await ownerPage.getByLabel(/^First Name/).fill('Tab');
    await ownerPage.getByLabel(/^Last Name/).fill('Invitee');
    await ownerPage.getByLabel(/^Email/).fill(email);
    await ownerPage.getByTestId('invite-team-member-submit').click();

    const row = ownerPage.getByTestId('team-access-table').getByRole('row').filter({ hasText: email });
    await expect(row.getByText('This event only')).toBeVisible();
  });
});
