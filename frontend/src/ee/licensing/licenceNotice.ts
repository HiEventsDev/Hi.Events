import {t} from "@lingui/macro";
import {i18n} from "@lingui/core";
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

const lockableFeatureName = (feature: LicensedFeature): string | null => {
    switch (feature) {
        case 'seating':
            return t`reserved seating`;
        case 'box_office':
            return t`box office`;
        case 'enterprise_sso':
            return t`enterprise single sign-on`;
        default:
            return null;
    }
};

export const licensedFeatureList = (features: LicensedFeature[]): string | null => {
    const names = features
        .map(lockableFeatureName)
        .filter((name): name is string => name !== null);

    if (names.length === 0) {
        return null;
    }

    return new Intl.ListFormat(i18n.locale, {type: 'conjunction'}).format(names);
};

export const licensedFeatureLabel = (feature: string): string => {
    switch (feature) {
        case 'seating':
            return t`Reserved seating`;
        case 'box_office':
            return t`Box office`;
        case 'enterprise_sso':
            return t`Enterprise single sign-on`;
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
