import {t} from "@lingui/macro";

export interface DeviceDescription {
    label: string;
    isMobile: boolean;
}

const browsers: [RegExp, string][] = [
    [/Edg\//, 'Edge'],
    [/OPR\/|Opera/, 'Opera'],
    [/Firefox\//, 'Firefox'],
    [/Chrome\//, 'Chrome'],
    [/Safari\//, 'Safari'],
];

const operatingSystems: [RegExp, string][] = [
    [/iPhone|iPad|iPod/, 'iOS'],
    [/Android/, 'Android'],
    [/Mac OS X|Macintosh/, 'macOS'],
    [/Windows/, 'Windows'],
    [/CrOS/, 'ChromeOS'],
    [/Linux/, 'Linux'],
];

export const describeDevice = (userAgent: string | null): DeviceDescription => {
    if (!userAgent) {
        return {label: t`Unknown device`, isMobile: false};
    }

    const browser = browsers.find(([pattern]) => pattern.test(userAgent))?.[1];
    const os = operatingSystems.find(([pattern]) => pattern.test(userAgent))?.[1];
    const label = browser && os
        ? t`${browser} on ${os}`
        : browser ?? os ?? t`Unknown device`;

    return {
        label,
        isMobile: /Mobi|iPhone|iPad|Android/.test(userAgent),
    };
};
