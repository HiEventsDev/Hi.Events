import {t} from "@lingui/macro";

export const planDisplayName = (name?: string | null): string => {
    switch (name) {
        case 'Pro':
            return t`Pro`;
        case 'Default':
            return t`Default`;
        default:
            return name ?? '';
    }
};
