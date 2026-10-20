export const LOGIN_PATH = "/auth/login";
export const TWO_FACTOR_SETUP_PATH = "/auth/two-factor-setup";
const ACCOUNT_DELETION_PATH = "/settings/account#delete-account";
const ACCESS_ERROR_CODES = ['FEATURE_UNAVAILABLE', 'ACCESS_RESTRICTED', 'PERMISSION_REQUIRED', 'READ_ONLY_ROLE', 'IMPERSONATION_NOT_ALLOWED'];

const ALLOWED_UNAUTHENTICATED_PATHS = [
    'auth/login',
    'accept-invitation',
    'register',
    'forgot-password',
    'auth',
    'account/payment',
    'checkout',
    'box-office',
    '/event/',
    'print',
    '/order/',
    'widget',
    '/product/',
    'check-in',
    '/events/',
    'my-tickets',
    'emails/unsubscribe',
    'sso/callback',
];

export type AuthErrorAction =
    | { type: 'none' }
    | { type: 'redirect', to: string }
    | { type: 'login' };

const NONE: AuthErrorAction = {type: 'none'};

const isPublicPath = (path: string) => !path.startsWith('/manage/')
    && ALLOWED_UNAUTHENTICATED_PATHS.some(allowed => path.includes(allowed));

export const resolveAuthErrorAction = (status: number, errorCode: string | undefined, currentPath: string): AuthErrorAction => {
    if (status === 403 && errorCode === 'ACCOUNT_PENDING_DELETION') {
        return currentPath.startsWith('/settings') ? NONE : {type: 'redirect', to: ACCOUNT_DELETION_PATH};
    }

    if (status === 403 && errorCode === 'TWO_FACTOR_SETUP_REQUIRED') {
        return currentPath.startsWith(TWO_FACTOR_SETUP_PATH) ? NONE : {type: 'redirect', to: TWO_FACTOR_SETUP_PATH};
    }

    if (status === 403 && errorCode && ACCESS_ERROR_CODES.includes(errorCode)) {
        return NONE;
    }

    if ((status === 401 || status === 403) && !isPublicPath(currentPath)) {
        return {type: 'login'};
    }

    return NONE;
};
