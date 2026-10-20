import {publicApi} from './public-client.ts';

export const lifecycleEmailClient = {
    unsubscribe: async (token: string) => {
        await publicApi.post(`lifecycle-emails/unsubscribe/${token}`);
    },
}
