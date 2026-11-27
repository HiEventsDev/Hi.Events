import type { Locator, Page } from '@playwright/test';

export class AudiencePage {
  constructor(private readonly page: Page, private readonly organizerId: number) {}

  private path(section = ''): string {
    return `/manage/organizer/${this.organizerId}/audience${section}`;
  }

  async gotoOverview(): Promise<void> {
    await this.page.goto(this.path());
  }

  async gotoContacts(query = ''): Promise<void> {
    await this.page.goto(this.path(`/contacts${query}`));
  }

  async gotoContactsMatching(term: string): Promise<void> {
    await this.gotoContacts(`?query=${encodeURIComponent(term)}`);
  }

  async gotoSegments(): Promise<void> {
    await this.page.goto(this.path('/segments'));
  }

  async gotoCampaigns(): Promise<void> {
    await this.page.goto(this.path('/campaigns'));
  }

  async gotoAutomations(): Promise<void> {
    await this.page.goto(this.path('/automations'));
  }

  async gotoSuppressed(): Promise<void> {
    await this.gotoContacts('?segment=suppressed');
  }

  async gotoForms(): Promise<void> {
    await this.page.goto(this.path('/forms'));
  }

  startButton(): Locator {
    return this.page.getByTestId('audience-start-button');
  }

  tab(name: string): Locator {
    return this.page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name, exact: true });
  }

  automationsTab(): Locator {
    return this.page.getByTestId('audience-nav-automations');
  }

  async search(term: string): Promise<void> {
    await this.page.getByTestId('audience-contact-search').fill(term);
  }

  contactTotal(): Locator {
    return this.page.getByTestId('audience-contact-total');
  }

  contactRows(): Locator {
    return this.page.getByTestId('audience-contact-row');
  }

  contactRow(text: string): Locator {
    return this.contactRows().filter({ hasText: text });
  }

  rowCheckbox(text: string): Locator {
    return this.contactRow(text).getByRole('checkbox');
  }

  selectPageCheckbox(): Locator {
    return this.page.getByRole('checkbox', { name: 'Select all rows on this page' });
  }

  bulkBar(): Locator {
    return this.page.getByTestId('audience-bulk-bar');
  }

  selectAllMatchingButton(): Locator {
    return this.page.getByTestId('audience-select-all-matching');
  }

  bulkConfirmButton(): Locator {
    return this.page.getByTestId('audience-bulk-confirm');
  }

  async bulkTag(action: 'Add tag' | 'Remove tag', tag: string): Promise<void> {
    await this.bulkBar().getByRole('button', { name: action }).click();
    const search = this.page.getByPlaceholder(action === 'Add tag' ? 'Find or create a tag' : 'Find a tag');
    await search.fill(tag);
    await search.press('Enter');
  }

  async bulkMoreAction(name: 'Unsubscribe' | 'Delete'): Promise<void> {
    await this.page.getByTestId('audience-bulk-more').click();
    await this.page.getByTestId(name === 'Delete' ? 'audience-bulk-delete' : 'audience-bulk-unsubscribe').click();
  }

  importButton(): Locator {
    return this.page.getByTestId('audience-contact-import-button');
  }

  addContactButton(): Locator {
    return this.page.getByRole('button', { name: 'Add contact' });
  }

  exportButton(): Locator {
    return this.page.getByRole('menuitem', { name: 'Export this list' });
  }

  filterButton(): Locator {
    return this.page.getByRole('button', { name: /^Filter/ });
  }

  filterPanel(): Locator {
    return this.page.getByRole('region', { name: 'Filter contacts' });
  }

  segmentCreateButton(): Locator {
    return this.page.getByTestId('segment-create-button');
  }

  campaignCreateButton(): Locator {
    return this.page.getByTestId('campaign-create-button');
  }

  async startBlankCampaign(): Promise<void> {
    await this.gotoCampaigns();
    await this.campaignCreateButton().click();
    await this.page.getByTestId('campaign-picker-start').click();
    await this.page.waitForURL(/\/audience\/campaigns\/new/);
  }

  async composeCampaign(subject: string, body: string): Promise<void> {
    await this.startBlankCampaign();
    await this.page.getByTestId('campaign-subject').fill(subject);
    await this.page.getByTestId('campaign-body').fill(body);
  }

  async sendCampaignNow(): Promise<number> {
    await this.page.getByTestId('campaign-review-send').click();
    await this.page.getByTestId('campaign-send-confirm').click();
    await this.page.waitForURL(/\/audience\/campaigns\/\d+$/);
    return this.campaignIdFromUrl();
  }

  campaignIdFromUrl(): number {
    return Number(new URL(this.page.url()).pathname.split('/').pop());
  }

  campaignStatus(): Locator {
    return this.page.getByTestId('campaign-status');
  }

  campaignRow(text: string): Locator {
    return this.page.getByRole('row').filter({ hasText: text });
  }

  async addContact(email: string, firstName: string, lastName: string): Promise<void> {
    await this.addContactButton().click();
    const modal = this.page.getByRole('dialog', { name: 'Add a contact' });
    await modal.getByLabel('Email address').fill(email);
    await modal.getByLabel('First name').fill(firstName);
    await modal.getByLabel('Last name').fill(lastName);
    await modal.getByRole('button', { name: 'Add contact' }).click();
  }

  async chooseCsv(dialog: Locator, name: string, csv: string): Promise<void> {
    const chooser = this.page.waitForEvent('filechooser');
    await dialog.getByLabel('CSV file').click();
    await (await chooser).setFiles({ name, mimeType: 'text/csv', buffer: Buffer.from(csv) });
  }

  async openSuppressionImport(): Promise<Locator> {
    await this.importButton().click();
    await this.page.getByTestId('audience-import-suppressions-button').click();
    return this.page.getByRole('dialog', { name: 'Import suppressions' });
  }

  suppressionCounts(): Locator {
    return this.page.getByTestId('audience-suppression-counts');
  }

  async finishImport(): Promise<void> {
    await this.page.getByTestId('audience-import-done').click();
  }

  drawer(): Locator {
    return this.page.getByRole('dialog').filter({ has: this.page.getByRole('heading', { name: 'Email status', exact: true }) });
  }

  segmentSelect(): Locator {
    return this.page.getByRole('combobox', { name: 'Contacts to show', exact: true });
  }

  drawerSection(title: string): Locator {
    return this.drawer().locator('section').filter({ has: this.page.getByRole('heading', { name: title, exact: true }) });
  }

  marketingStatus(): Locator {
    return this.drawerSection('Email status');
  }

  emailRow(email: string): Locator {
    return this.drawerSection('Email addresses').getByText(email, { exact: true }).locator('xpath=..');
  }

  primaryMark(email: string): Locator {
    return this.emailRow(email).getByText('Primary', { exact: true });
  }

  noteInput(): Locator {
    return this.drawer().getByRole('textbox', { name: 'Add a note' });
  }

  tagInput(): Locator {
    return this.drawer().getByRole('combobox', { name: 'Tags' });
  }

  contactsMoreActions(): Locator {
    return this.page.getByTestId('audience-contacts-more');
  }

  moreActions(): Locator {
    return this.drawer().getByRole('button', { name: 'More actions' });
  }

  async openDrawerAction(name: string): Promise<void> {
    await this.moreActions().click();
    await this.page.getByRole('menuitem', { name }).click();
  }

  async confirm(label: string): Promise<void> {
    await this.page.getByRole('dialog')
      .filter({ has: this.page.getByRole('button', { name: 'Cancel', exact: true }) })
      .getByRole('button', { name: label, exact: true }).click();
  }
}
