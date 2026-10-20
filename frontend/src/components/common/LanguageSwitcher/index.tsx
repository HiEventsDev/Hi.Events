import {Select} from "@mantine/core";
import {availableLocales, dynamicActivateLocale, getClientLocale, getLocaleName, SupportedLocales} from "../../../locales.ts";
import {t} from "@lingui/macro";
import {IconWorld} from "@tabler/icons-react";
import {useLingui} from "@lingui/react";

export const LanguageSwitcher = () => {
    useLingui();

    return (
        <>
            <Select
                leftSection={<IconWorld size={15} color={'#ccc'}/>}
                width={180}
                size={'xs'}
                required
                data={availableLocales.map(locale => ({
                    value: locale,
                    label: getLocaleName(locale as SupportedLocales),
                }))}
                defaultValue={getClientLocale()}
                placeholder={t`English`}
                onChange={(value) => {
                    if (!value) return;
                    document.cookie = `locale=${value};path=/;max-age=31536000`;
                    dynamicActivateLocale(value).finally(() => {
                        window.location.href = window.location.pathname + window.location.search;
                    });
                }}
            />
        </>
    )
}
