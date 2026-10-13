import {api} from './client.ts';

export const lifecycleEmailClient = {
    unsubscribe: async (token: string) => {
        await api.post(`public/lifecycle-emails/unsubscribe/${token}`);
    },
}
