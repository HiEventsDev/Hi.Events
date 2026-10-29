import { test, expect } from '../../fixtures';
import { BoxOfficeManagePage } from '../../pages/box-office-manage.page';
import { createDraftEventWithTicket } from '../../api/factory';
import { openAuthedPage } from '../../fixtures/auth.fixture';
import { uniqueEmail, uniqueShort } from '../../utils/unique';

const MEMBER_PASSWORD = 'MemberPass123!';

test.describe('box office management', () => {
  test('every event starts with a default box office', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);

    const boxOffices = new BoxOfficeManagePage(authedPage);
    await boxOffices.goto(event.eventId);

    await expect(boxOffices.row('Box office').getByText('Default')).toBeVisible();
    await expect(boxOffices.row('Box office').getByText('No PIN')).toBeVisible();
    await expect(boxOffices.openDefaultButton()).toBeEnabled();
  });

  test('a team member with the organizer role can open the Box Office page', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const memberEmail = uniqueEmail('door-manager');
    await freshAccount.api.inviteUser({ first_name: 'Casey', last_name: 'Member', email: memberEmail, role: 'ORGANIZER' });
    const inviteUrl = await mailpit.waitForLink(memberEmail, /accept-invitation/, { subjectContains: 'invited to join' });
    const accepted = await publicApi.post(`auth/invitation/${inviteUrl.pathname.split('/').pop()}`, {
      data: {
        first_name: 'Casey',
        last_name: 'Member',
        password: MEMBER_PASSWORD,
        password_confirmation: MEMBER_PASSWORD,
        timezone: 'UTC',
      },
    });
    expect(accepted.ok()).toBeTruthy();
    const login = await publicApi.post('auth/login', { data: { email: memberEmail, password: MEMBER_PASSWORD } });
    expect(login.ok()).toBeTruthy();
    const { token } = await login.json();
    const event = await createDraftEventWithTicket(freshAccount.api, freshAccount.organizerId);

    const memberPage = await openAuthedPage(browser, token);
    const boxOffices = new BoxOfficeManagePage(memberPage);
    await boxOffices.goto(event.eventId);

    await expect(boxOffices.row('Box office').getByText('Default')).toBeVisible();
    await expect(memberPage).toHaveURL(new RegExp(`/manage/event/${event.eventId}/box-office`));
    await memberPage.context().close();
  });

  test('opening a box office without a PIN prompts for one first', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);

    const boxOffices = new BoxOfficeManagePage(authedPage);
    await boxOffices.goto(event.eventId);

    await expect(boxOffices.row('Box office').getByText('No PIN')).toBeVisible();
    await boxOffices.openDefaultButton().click();

    await expect(boxOffices.pinValue()).toHaveText(/^\d{6}$/);
    await boxOffices.dismissPinModal();

    await expect(boxOffices.row('Box office').getByText('No PIN')).toHaveCount(0);
  });

  test('creating a box office reveals the PIN once and it can be reset later', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const name = uniqueShort('North Gate');

    const boxOffices = new BoxOfficeManagePage(authedPage);
    await boxOffices.goto(event.eventId);
    await boxOffices.create(name);

    await expect(boxOffices.pinValue()).toHaveText(/^\d{6}$/);
    const firstPin = await boxOffices.pinValue().innerText();
    await expect(authedPage.getByRole('dialog')).toContainText("won't be shown again");
    await boxOffices.dismissPinModal();

    await expect(boxOffices.row(name).getByText('No PIN')).toHaveCount(0);

    await boxOffices.openRowAction(name, 'Reset PIN');
    await authedPage.getByRole('button', { name: 'Confirm' }).click();
    await expect(boxOffices.pinValue()).toHaveText(/^\d{6}$/);
    expect(await boxOffices.pinValue().innerText()).not.toBe(firstPin);
    await boxOffices.dismissPinModal();
  });

  test('an organizer creates, renames and deletes a box office', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const name = uniqueShort('West Gate');
    const newName = uniqueShort('East Gate');

    const boxOffices = new BoxOfficeManagePage(authedPage);
    await boxOffices.goto(event.eventId);
    await boxOffices.create(name);
    await boxOffices.dismissPinModal();

    await expect(boxOffices.row(name)).toBeVisible();

    await boxOffices.openRowAction(name, 'Edit Box Office');
    await expect(boxOffices.editNameInput()).toHaveValue(name);
    await boxOffices.editNameInput().fill(newName);
    await boxOffices.submitEdit();

    await expect(boxOffices.row(newName)).toBeVisible();
    await expect(boxOffices.row(name)).toHaveCount(0);

    await boxOffices.openRowAction(newName, 'Delete Box Office');
    await authedPage.getByRole('button', { name: 'Confirm' }).click();

    await expect(boxOffices.row(newName)).toHaveCount(0);
  });

  test('the default box office cannot be deleted from the row menu', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);

    const boxOffices = new BoxOfficeManagePage(authedPage);
    await boxOffices.goto(event.eventId);
    await boxOffices.row('Box office').getByTestId('box-office-actions-menu').click();

    await expect(authedPage.getByRole('menuitem', { name: 'Delete Box Office' })).toHaveCount(0);
  });
});
