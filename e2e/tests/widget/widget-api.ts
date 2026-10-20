import { type APIRequest, type APIRequestContext, expect } from '@playwright/test';
import { API_BASE_URL } from '../../utils/env';

export interface SavedWidget {
  id: number;
  short_id: string;
  name: string;
  settings: Record<string, unknown>;
  stats: { views: number; orders: number; revenue?: Record<string, number> };
}

const data = async <T>(response: Promise<import('@playwright/test').APIResponse>): Promise<T> => {
  const resolved = await response;
  expect(resolved.ok(), `${resolved.url()} ${resolved.status()} ${await resolved.text()}`).toBeTruthy();
  return (await resolved.json()).data as T;
};

export class WidgetApi {
  private constructor(private readonly request: APIRequestContext) {}

  static async create(playwright: { request: APIRequest }, token: string): Promise<WidgetApi> {
    return new WidgetApi(await playwright.request.newContext({
      baseURL: API_BASE_URL,
      ignoreHTTPSErrors: true,
      extraHTTPHeaders: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    }));
  }

  createOrganizerWidget(organizerId: number, payload: { name: string; type: 'EVENTS'; settings: Record<string, unknown> }): Promise<SavedWidget> {
    return data(this.request.post(`organizers/${organizerId}/widgets`, { data: payload }));
  }

  listEventWidgets(eventId: number): Promise<SavedWidget[]> {
    return data(this.request.get(`events/${eventId}/widgets`));
  }

  listOrganizerWidgets(organizerId: number): Promise<SavedWidget[]> {
    return data(this.request.get(`organizers/${organizerId}/widgets`));
  }

  async deleteProduct(eventId: number, productId: number): Promise<void> {
    const response = await this.request.delete(`events/${eventId}/products/${productId}`);
    expect(response.ok(), await response.text()).toBeTruthy();
  }

  dispose(): Promise<void> {
    return this.request.dispose();
  }
}
