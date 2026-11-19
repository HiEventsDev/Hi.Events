import type { APIRequest, APIRequestContext, Locator, Page } from '@playwright/test';
import { expect, type FreshAccount } from '../../fixtures';
import type { AudiencePage } from '../../pages/audience.page';
import { runSql } from '../../utils/db';
import { uniqueEmail } from '../../utils/unique';
import { dataOf, ok, withOrganizerApi, type SegmentRule } from './audience.shared';

export const PHONE_VIEWPORT = { width: 375, height: 812 };

export interface SegmentDefinition {
  match: 'all' | 'any';
  rules: SegmentRule[];
}

export interface ImportOptions {
  fileName?: string;
  tag?: string;
  consentAttested?: boolean;
}

export interface ImportedFile {
  id: number;
  status: 'PROCESSING' | 'COMPLETED' | 'FAILED';
  created_count: number;
}

export class ContactsApi {
  constructor(private readonly request: APIRequestContext, private readonly organizerId: number) {}

  private path(section: string): string {
    return `organizers/${this.organizerId}/crm/${section}`;
  }

  importContacts(csv: string, options: ImportOptions = {}): Promise<ImportedFile> {
    return dataOf<ImportedFile>(this.request.post(this.path('imports'), {
      multipart: {
        file: { name: options.fileName ?? 'contacts.csv', mimeType: 'text/csv', buffer: Buffer.from(csv) },
        ...(options.tag ? { tag_name: options.tag } : {}),
        ...(options.consentAttested ? { consent_attested: '1' } : {}),
      },
    }));
  }

  async findTagId(name: string): Promise<number> {
    const tags = await dataOf<{ id: number; name: string }[]>(this.request.get(this.path('tags')));
    const tag = tags.find((candidate) => candidate.name === name);
    if (!tag) {
      throw new Error(`No tag named ${name}`);
    }
    return tag.id;
  }

  async deleteContact(contactId: number): Promise<void> {
    await ok(this.request.delete(this.path(`contacts/${contactId}`)));
  }

  createSegment(name: string, definition: SegmentDefinition): Promise<{ id: number }> {
    return dataOf<{ id: number }>(this.request.post(this.path('segments'), { data: { name, description: null, definition } }));
  }

  async deleteSegment(segmentId: number): Promise<void> {
    await ok(this.request.delete(this.path(`segments/${segmentId}`)));
  }

  createDraftCampaign(name: string, segmentId: number): Promise<{ id: number }> {
    return dataOf<{ id: number }>(this.request.post(this.path('campaigns'), {
      data: { name, subject: name, body: '<p>Hello</p>', segment_id: segmentId },
    }));
  }
}

export const withContactsApi = <T>(
  playwright: { request: APIRequest },
  account: FreshAccount,
  run: (contactsApi: ContactsApi) => Promise<T>,
  organizerId: number = account.organizerId,
): Promise<T> => withOrganizerApi(playwright, account, (request) => run(new ContactsApi(request, organizerId)));

export const contactsCsv = (rows: { email: string; firstName?: string; lastName?: string }[]): string =>
  ['Email,First Name,Last Name', ...rows.map((row) => `${row.email},${row.firstName ?? ''},${row.lastName ?? ''}`)].join('\n') + '\n';

export const familyCsv = (lastName: string, count: number): string =>
  contactsCsv(Array.from({ length: count }, (_, index) => ({
    email: uniqueEmail(`member${index}`),
    firstName: `Member${String(index + 1).padStart(3, '0')}`,
    lastName,
  })));

export const importFamily = (
  playwright: { request: APIRequest },
  account: FreshAccount,
  lastName: string,
  count: number,
): Promise<ImportedFile> => withContactsApi(playwright, account, (contactsApi) => contactsApi.importContacts(familyCsv(lastName, count)));

export const expectNoSidewaysScroll = async (page: Page): Promise<void> => {
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
};

export const segmentRow = (page: Page, name: string): Locator =>
  page.getByTestId('segment-row').filter({ has: page.getByRole('link', { name, exact: true }) });

export const readyMadeSegment = (page: Page, name: string): Locator =>
  page.getByRole('link').filter({ hasText: new RegExp(`^${name}`) });

export const chooseRule = async (page: Page, builder: Locator, field: string): Promise<void> => {
  await builder.getByPlaceholder('Choose what to filter by').click();
  await page.getByRole('option', { name: field, exact: true }).click();
};

export const ruleValue = (builder: Locator): Locator => builder.getByRole('textbox', { name: 'Value' });

export const chooseRuleOption = async (page: Page, builder: Locator, option: string): Promise<void> => {
  await builder.getByLabel('Options').last().click();
  await page.getByRole('option', { name: option, exact: true }).click();
  await page.keyboard.press('Escape');
};

export const cellUnder = async (page: Page, row: Locator, column: string): Promise<Locator> => {
  const headers = page.getByRole('columnheader');
  await expect(headers.filter({ hasText: column }).first()).toBeVisible();
  const index = await headers.evaluateAll(
    (cells, name) => cells.findIndex((cell) => (cell.textContent ?? '').trim().startsWith(name)),
    column,
  );
  return row.getByRole('cell').nth(index);
};

export const openManageTags = async (page: Page, audience: AudiencePage): Promise<Locator> => {
  await audience.contactsMoreActions().click();
  await page.getByRole('menuitem', { name: 'Manage tags' }).click();
  return page.getByRole('dialog', { name: 'Manage tags' });
};

export const tagRow = (manage: Locator, name: string): Locator =>
  manage.locator('form').filter({ has: manage.page().locator(`input[aria-label="Tag name"][value="${name}"]`) });

export const closeDialog = async (dialog: Locator): Promise<void> => {
  await dialog.getByRole('button', { name: 'Close', exact: true }).filter({ hasText: 'Close' }).click();
  await expect(dialog).toBeHidden();
};

export const openImportContacts = async (page: Page, audience: AudiencePage): Promise<Locator> => {
  await audience.importButton().click();
  await page.getByTestId('audience-import-contacts').click();
  return page.getByRole('dialog', { name: 'Import contacts' });
};

export const importThroughDialog = async (
  page: Page,
  audience: AudiencePage,
  csv: string,
  options: ImportOptions = {},
): Promise<Locator> => {
  const dialog = await openImportContacts(page, audience);
  await audience.chooseCsv(dialog, options.fileName ?? 'contacts.csv', csv);
  if (options.tag) {
    await dialog.getByLabel('Tag these contacts').fill(options.tag);
  }
  if (options.consentAttested) {
    await dialog.getByRole('checkbox', { name: 'These people opted in to marketing emails from us' }).check();
  }
  await page.getByTestId('audience-import-submit').click();
  return page.getByTestId('audience-import-result');
};

export const breakdownValue = (result: Locator, label: string): Locator =>
  result.locator('div').filter({ has: result.page().getByText(label, { exact: true }) }).last().locator('dd');

const SHORT_ID = /^[A-Za-z0-9_]+$/;

export function moveOrderIntoThePast(shortId: string, days: number): void {
  if (!SHORT_ID.test(shortId) || !Number.isInteger(days)) {
    throw new Error(`Refusing to move order ${shortId} by ${days} days`);
  }
  const updated = runSql(`
    WITH moved AS (
      UPDATE orders SET created_at = created_at - INTERVAL '${days} days' WHERE short_id = '${shortId}' RETURNING id
    )
    UPDATE attendees SET created_at = created_at - INTERVAL '${days} days' WHERE order_id IN (SELECT id FROM moved) RETURNING order_id`).trim();
  if (updated === '') {
    throw new Error(`No order found with short id ${shortId}`);
  }
}
