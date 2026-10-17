import {SsoIntent, SsoStartResponse} from "../types.ts";

const FLOW_PREFIX = 'hi_sso_flow:';
const LAST_PROVIDER_KEY = 'hi_last_sso_provider';
const REAUTH_PROOF_KEY = 'hi_sso_reauth_proof';
const SIGNUP_CONVERSION_KEY = 'hi_sso_signup_conversion';
const CONNECTION_SESSION_KEY = 'hi_sso_connection_session';
const SILENT_REAUTH_KEY = 'hi_sso_silent_reauth';
const LAST_SILENT_REAUTH_KEY = 'hi_sso_last_silent_reauth';
const SILENT_REAUTH_COOLDOWN_MS = 5 * 60 * 1000;
const AUTH_NOTICE_KEY = 'hi_auth_notice';
const CONNECTION_KEY_PREFIX = 'connection-';
export const SSO_TEST_MESSAGE = 'hi-sso-test';
const REAUTH_PROOF_LIFETIME_MS = 4 * 60 * 1000;
const IN_APP_BROWSER_PATTERN = /FBAN|FBAV|FB_IAB|Instagram|BytedanceWebview|musical_ly|TikTok|LinkedInApp|Snapchat|Line\//i;

export interface SsoFlow {
    binding: string;
    provider: string;
    providerName: string;
    intent: SsoIntent;
    returnPath?: string;
    invitationToken?: string;
    partnerInviteToken?: string;
    timezone?: string;
}

const readStorage = (storage: () => Storage, key: string): string | null => {
    try {
        return storage().getItem(key);
    } catch {
        return null;
    }
};

const writeStorage = (storage: () => Storage, key: string, value: string | null) => {
    try {
        if (value === null) {
            storage().removeItem(key);
        } else {
            storage().setItem(key, value);
        }
    } catch {
        return;
    }
};

const session = () => window.sessionStorage;

export const readSessionValue = (key: string): string | null => readStorage(session, key);

export const writeSessionValue = (key: string, value: string | null) => writeStorage(session, key, value);
const local = () => window.localStorage;

export const storeSsoFlow = (response: SsoStartResponse, flow: Omit<SsoFlow, 'binding'>, sharedWithPopup = false) => {
    const state = new URL(response.authorization_url).searchParams.get('state');

    if (state) {
        writeStorage(sharedWithPopup ? local : session, FLOW_PREFIX + state, JSON.stringify({...flow, binding: response.binding}));
    }
};

export const redirectToSso = (response: SsoStartResponse, flow: Omit<SsoFlow, 'binding'>) => {
    storeSsoFlow(response, flow);

    window.location.assign(response.authorization_url);
};

export const getSsoFlow = (state: string | null): SsoFlow | null => {
    if (!state) {
        return null;
    }

    const stored = readStorage(session, FLOW_PREFIX + state) ?? readStorage(local, FLOW_PREFIX + state);

    try {
        return stored ? JSON.parse(stored) as SsoFlow : null;
    } catch {
        return null;
    }
};

export const clearSsoFlow = (state: string | null) => {
    if (state) {
        writeStorage(session, FLOW_PREFIX + state, null);
        writeStorage(local, FLOW_PREFIX + state, null);
    }
};

export const isAccountConnection = (provider: string | null | undefined): boolean => !!provider?.startsWith(CONNECTION_KEY_PREFIX);

export const rememberConnectionSession = (provider: string) => writeStorage(local, CONNECTION_SESSION_KEY, provider);

export const forgetConnectionSession = () => writeStorage(local, CONNECTION_SESSION_KEY, null);

export const queueSilentReauthAfterExpiry = (): boolean => {
    const provider = readStorage(local, CONNECTION_SESSION_KEY);

    if (!provider) {
        return false;
    }

    writeStorage(local, CONNECTION_SESSION_KEY, null);

    const lastAttempt = Number(readStorage(session, LAST_SILENT_REAUTH_KEY) ?? 0);
    if (Date.now() - lastAttempt < SILENT_REAUTH_COOLDOWN_MS) {
        return false;
    }

    writeStorage(session, LAST_SILENT_REAUTH_KEY, String(Date.now()));
    writeStorage(session, SILENT_REAUTH_KEY, provider);

    return true;
};

export const takeSilentReauthProvider = (): string | null => {
    const provider = readStorage(session, SILENT_REAUTH_KEY);
    writeStorage(session, SILENT_REAUTH_KEY, null);

    return provider;
};

export const setAuthNotice = (code: string) => writeStorage(session, AUTH_NOTICE_KEY, code);

export const takeAuthNotice = (): string | null => {
    const notice = readStorage(session, AUTH_NOTICE_KEY);
    writeStorage(session, AUTH_NOTICE_KEY, null);

    return notice;
};

export const rememberLastSsoProvider = (provider: string) => writeStorage(local, LAST_PROVIDER_KEY, provider);

export const getLastSsoProvider = (): string | null => typeof window === 'undefined' ? null : readStorage(local, LAST_PROVIDER_KEY);

export const storeReauthProof = (proof: string) => {
    writeStorage(session, REAUTH_PROOF_KEY, JSON.stringify({proof, expiresAt: Date.now() + REAUTH_PROOF_LIFETIME_MS}));
};

export const clearReauthProof = () => writeStorage(session, REAUTH_PROOF_KEY, null);

export const getReauthProof = (): string | null => {
    const stored = readStorage(session, REAUTH_PROOF_KEY);

    try {
        const parsed = stored ? JSON.parse(stored) as { proof: string, expiresAt: number } : null;

        return parsed && parsed.expiresAt > Date.now() ? parsed.proof : null;
    } catch {
        return null;
    }
};

export const markSsoSignupConversion = () => writeStorage(session, SIGNUP_CONVERSION_KEY, '1');

export const takeSsoSignupConversion = (): boolean => {
    const pending = readStorage(session, SIGNUP_CONVERSION_KEY) === '1';
    writeStorage(session, SIGNUP_CONVERSION_KEY, null);

    return pending;
};

export const isInAppBrowser = (): boolean => {
    return typeof navigator !== 'undefined' && IN_APP_BROWSER_PATTERN.test(navigator.userAgent);
};
