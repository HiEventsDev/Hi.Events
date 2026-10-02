import {t} from "@lingui/macro";
import {LicensedFeature, LicenceSummary} from "../../types.ts";

export type LicenceNoticeKind = 'invalid' | 'locked' | 'dev' | 'grace' | 'lapsed';

export type LicenceTone = 'danger' | 'warning' | 'info';

export interface LicenceNotice {
    kind: LicenceNoticeKind;
    tone: LicenceTone;
}

export const LICENCE_TONE_COLORS: Record<LicenceTone, string> = {
    danger: 'red',
    warning: 'orange',
    info: 'blue',
};

export const licensedFeatureList = (features: LicensedFeature[]): string | null => {
    const seating = features.includes('seating');
    const boxOffice = features.includes('box_office');

    if (seating && boxOffice) {
        return t`reserved seating and box office`;
    }
    if (seating) {
        return t`reserved seating`;
    }
    if (boxOffice) {
        return t`box office`;
    }

    return null;
};

export const licensedFeatureLabel = (feature: string): string => {
    switch (feature) {
        case 'seating':
            return t`Reserved seating`;
        case 'box_office':
            return t`Box office`;
        case 'white_label':
            return t`White-label`;
        default:
            return feature;
    }
};

export const licenceStatusLabel = (kind: LicenceNoticeKind): string => {
    switch (kind) {
        case 'invalid':
            return t`Check key`;
        case 'locked':
            return t`Setup locked`;
        case 'dev':
            return t`Development`;
        case 'grace':
            return t`Grace period`;
        case 'lapsed':
            return t`Expired`;
    }
};

export const licenceNoticeFor = (licence: LicenceSummary): LicenceNotice | null => {
    if (licence.invalid_reason) {
        return {kind: 'invalid', tone: 'danger'};
    }

    if (licence.features_in_use.length === 0) {
        return null;
    }

    const hasLockableSetup = licensedFeatureList(licence.features_in_use) !== null;

    switch (licence.status) {
        case 'GRACE':
            return {kind: 'grace', tone: 'warning'};
        case 'LAPSED':
            return {kind: 'lapsed', tone: 'danger'};
        case 'NONE':
            return hasLockableSetup ? {kind: 'locked', tone: 'danger'} : null;
        case 'DEV':
            return hasLockableSetup ? {kind: 'dev', tone: 'info'} : null;
        default:
            return null;
    }
};
