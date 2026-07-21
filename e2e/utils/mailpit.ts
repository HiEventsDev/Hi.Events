import type { APIRequestContext } from '@playwright/test';
import { MAILPIT_URL } from './env';

interface MailpitSummary {
  ID: string;
  Subject: string;
  To: { Address: string }[];
}

interface MailpitMessage {
  ID: string;
  Subject: string;
  Text: string;
  HTML: string;
}

export class MailpitClient {
  constructor(private readonly request: APIRequestContext) {}

  async search(toAddress: string): Promise<MailpitSummary[]> {
    const response = await this.request.get(
      `${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(`to:"${toAddress}"`)}`,
    );
    if (!response.ok()) return [];
    const body = (await response.json()) as { messages?: MailpitSummary[] };
    return body.messages ?? [];
  }

  async waitForMessage(
    toAddress: string,
    opts: { timeoutMs?: number; intervalMs?: number; subjectContains?: string } = {},
  ): Promise<MailpitSummary> {
    const { timeoutMs = 15_000, intervalMs = 500, subjectContains } = opts;
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
      const messages = await this.search(toAddress);
      const match = subjectContains
        ? messages.find((m) => m.Subject.includes(subjectContains))
        : messages[0];
      if (match) return match;
      await new Promise((resolve) => setTimeout(resolve, intervalMs));
    }

    throw new Error(
      `No email to ${toAddress}${subjectContains ? ` matching "${subjectContains}"` : ''} arrived within ${timeoutMs}ms`,
    );
  }

  async getMessage(id: string): Promise<MailpitMessage> {
    const response = await this.request.get(`${MAILPIT_URL}/api/v1/message/${id}`);
    if (!response.ok()) {
      throw new Error(`Mailpit GET message/${id} → ${response.status()}: ${await response.text()}`);
    }
    return (await response.json()) as MailpitMessage;
  }

  async waitForVerificationCode(toAddress: string, opts: { timeoutMs?: number } = {}): Promise<string> {
    const summary = await this.waitForMessage(toAddress, opts);
    const message = await this.getMessage(summary.ID);
    const body = `${message.Text}\n${message.HTML}`;
    const match = body.match(/\b(\d{5})\b/);
    if (!match) {
      throw new Error(`Could not find a 5-digit verification code in the email to ${toAddress}`);
    }
    return match[1];
  }
}
