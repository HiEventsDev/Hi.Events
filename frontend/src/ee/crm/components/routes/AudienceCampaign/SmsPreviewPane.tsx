import {t} from "@lingui/macro";
import {SegmentedControl, Text} from "@mantine/core";
import {useState} from "react";
import {getConfig} from "../../../../../utilites/config.ts";
import {CrmSmsCountry, CrmSmsPreviewVariant} from "../../../types.ts";
import {smsCountryList, smsOptsOutByReply} from "../../../utilites/smsCountries.ts";
import {composeSmsPreview, measureSms, SMS_OPT_OUT_LINK_EXAMPLE} from "../../../utilites/smsSegments.ts";
import classes from "./Campaign.module.scss";

const smsFooter = (optsOutByReply: boolean): string => optsOutByReply
    ? t`Reply STOP to opt out.`
    : t`Opt out: ${SMS_OPT_OUT_LINK_EXAMPLE(getConfig('VITE_FRONTEND_URL') ?? '')}`;

export const typedSmsVariants = (organizerName: string, body: string, countries: CrmSmsCountry[]): CrmSmsPreviewVariant[] => {
    const byFooter = [countries.filter(country => !smsOptsOutByReply(country)), countries.filter(smsOptsOutByReply)]
        .filter(group => group.length > 0);

    return (byFooter.length > 0 ? byFooter : [[]]).map(group => {
        const text = composeSmsPreview(organizerName, body, smsFooter(group.some(smsOptsOutByReply)));
        const length = measureSms(text);

        return {countries: group, text, characters: length.characters, parts: length.segments, is_unicode: length.isUnicode};
    });
};

export const SmsPreviewPane = ({variants, isRendered, isSent, isHidden, unavailable}: {
    variants: CrmSmsPreviewVariant[],
    isRendered: boolean,
    isSent: boolean,
    isHidden: boolean,
    unavailable: 'loading' | 'error' | null,
}) => {
    const [selected, setSelected] = useState(0);
    const variant = variants[selected] ?? variants[0];

    if (unavailable) {
        return (
            <aside className={classes.smsPreview} data-hidden={isHidden || undefined} aria-label={t`Text message preview`}>
                <Text size="sm" c="dimmed" ta="center" maw={320}>
                    {unavailable === 'error' ? t`We couldn't load the preview. Please refresh the page.` : t`Loading preview…`}
                </Text>
            </aside>
        );
    }

    return (
        <aside className={classes.smsPreview} data-hidden={isHidden || undefined} aria-label={t`Text message preview`}>
            {variants.length > 1 ? (
                <SegmentedControl
                    size="xs"
                    value={String(variants.indexOf(variant))}
                    onChange={value => setSelected(Number(value))}
                    aria-label={t`Preview for`}
                    data={variants.map((item, index) => ({value: String(index), label: smsCountryList(item.countries)}))}
                />
            ) : variant.countries.length > 0 && (
                <span className={classes.smsVariantLabel}>{smsCountryList(variant.countries)}</span>
            )}
            <div className={classes.smsPhone}>
                <div className={classes.smsBubble} data-testid="campaign-sms-preview">{variant.text}</div>
            </div>
            <Text size="xs" c="dimmed" ta="center" maw={320}>
                {isRendered
                    ? (isSent
                        ? t`Shown with your own details. Each person got their own name and links, and the opt-out line in their language.`
                        : t`Shown with your own details. Each person gets their own name and links, and the opt-out line in their language.`)
                    : t`Placeholders like the first name are filled in for each person when the text is sent.`}
            </Text>
        </aside>
    );
};
