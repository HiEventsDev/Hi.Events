const GSM_BASIC = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
const GSM_EXTENDED = "^{}\\[~]|€";

const SINGLE_GSM = 160;
const MULTI_GSM = 153;
const SINGLE_UNICODE = 70;
const MULTI_UNICODE = 67;

interface SmsLength {
    characters: number;
    segments: number;
    isUnicode: boolean;
}

export const measureSms = (text: string): SmsLength => {
    const chars = Array.from(text);
    const isUnicode = chars.some(char => !GSM_BASIC.includes(char) && !GSM_EXTENDED.includes(char));

    if (isUnicode) {
        const units = text.length;
        return {characters: units, segments: units <= SINGLE_UNICODE ? 1 : Math.ceil(units / MULTI_UNICODE), isUnicode};
    }

    const septets = chars.reduce((total, char) => total + (GSM_EXTENDED.includes(char) ? 2 : 1), 0);

    return {characters: septets, segments: septets <= SINGLE_GSM ? 1 : Math.ceil(septets / MULTI_GSM), isUnicode};
};

export const SMS_OPT_OUT_LINK_EXAMPLE = (frontendUrl: string): string => `${frontendUrl.replace(/\/$/, '')}/u/1234567.a1b2c3d4e5f6`;

export const composeSmsPreview = (organizerName: string, body: string, footer: string): string =>
    `${organizerName.trim()}: ${body.trim()}\n${footer}`;
