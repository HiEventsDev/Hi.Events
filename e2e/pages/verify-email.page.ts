import type { Locator, Page } from '@playwright/test';
import type { MailpitClient } from '../utils/mailpit';

export class VerifyEmailDialog {
  constructor(private readonly page: Page) {}

  dialog(): Locator {
    return this.page.getByRole('dialog', { name: "Confirm it's you" });
  }

  async enterCode(code: string): Promise<void> {
    await this.dialog().getByLabel('Verification code').first().click();
    await this.page.keyboard.type(code);
  }

  async confirmWithEmailedCode(mailpit: MailpitClient, email: string): Promise<void> {
    await this.dialog().waitFor();
    await this.enterCode(await mailpit.waitForSelfServiceCode(email));
    await this.dialog().waitFor({ state: 'hidden' });
  }

  error(): Locator {
    return this.dialog().getByRole('alert');
  }

  resendButton(): Locator {
    return this.dialog().getByTestId('verify-email-resend-button');
  }
}
