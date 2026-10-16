import { expect, type Locator, type Page } from '@playwright/test';

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

const pickerDropdown = (page: Page): Locator =>
  page.getByRole('dialog').filter({ has: page.locator('select[data-select="year"]') }).last();

const timeFields = (dateField: Locator): Locator =>
  dateField.locator('xpath=ancestor::div[contains(@class, "mantine-InputWrapper-root")][2]');

const twelveHour = (hours: number): string => String(hours % 12 || 12).padStart(2, '0');

const meridiem = (hours: number): string => (hours < 12 ? 'AM' : 'PM');

export async function pickDate(page: Page, field: Locator, isoDate: string): Promise<void> {
  const [year, month, day] = isoDate.split('-').map(Number);
  await field.click();
  const dropdown = pickerDropdown(page);
  await expect(dropdown).toBeVisible();
  await dropdown.locator('select[data-select="year"]').selectOption(String(year));
  await dropdown.locator('select[data-select="month"]').selectOption(String(month - 1));
  await dropdown.getByRole('button', { name: `${day} ${MONTHS[month - 1]} ${year}`, exact: true }).click();
  await expect(dropdown).toBeHidden();
}

export async function pickDateTime(page: Page, field: Locator, localDateTime: string): Promise<void> {
  const [date, time] = localDateTime.split('T');
  const [hours, minutes] = time.split(':').map(Number);
  await pickDate(page, field, date);

  const wrapper = timeFields(field);
  const fields: [Locator, string, string][] = [
    [wrapper.getByLabel('Hours'), twelveHour(hours), twelveHour(hours)],
    [wrapper.getByLabel('Minutes'), String(minutes).padStart(2, '0'), String(minutes).padStart(2, '0')],
    [wrapper.getByLabel('AM/PM'), meridiem(hours).charAt(0), meridiem(hours)],
  ];
  for (const [input] of fields) {
    await input.click();
    await input.press('Backspace');
    await input.press('Backspace');
  }
  for (const [input, keys, expected] of fields) {
    await input.click();
    await input.pressSequentially(keys);
    await expect(input).toHaveValue(expected);
  }
  await page.keyboard.press('Tab');
}

export async function expectDateTime(page: Page, field: Locator, localDateTime: string): Promise<void> {
  const [date, time] = localDateTime.split('T');
  const [year, month, day] = date.split('-').map(Number);
  const [hours, minutes] = time.split(':').map(Number);
  const wrapper = timeFields(field);
  await expect(field).toHaveText(`${MONTHS[month - 1].slice(0, 3)} ${day}, ${year}`);
  await expect(wrapper.getByLabel('Hours')).toHaveValue(twelveHour(hours));
  await expect(wrapper.getByLabel('Minutes')).toHaveValue(String(minutes).padStart(2, '0'));
  await expect(wrapper.getByLabel('AM/PM')).toHaveValue(meridiem(hours));
}

export function pickerWeekdayHeaders(page: Page): Locator {
  return pickerDropdown(page).locator('thead th');
}
