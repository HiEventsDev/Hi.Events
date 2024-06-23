import {Select} from "@mantine/core";
import {dynamicActivateLocale, getClientLocale, localeToNameMap, SupportedLocales} from "../../../locales.ts";
import {t} from "@lingui/macro";
import {IconWorld} from "@tabler/icons-react";
import {useLingui} from "@lingui/react";

export const LanguageSwitcher = () => {
    useLingui();

    // Ideally these would be in the locales.ts file, but when they're there they don't translate
    const getLocaleName = (locale: SupportedLocales): string => {
        switch (locale) {
            case "de":
                return t`German`;
            case "en":
                return t`English`;
            case "es":
                return t`Spanish`;
            case "fr":
                return t`French`;
            case "pt":
                return t`Portuguese`;
            case "pt-br":
                return t`Brazilian Portuguese`;
            case "zh-cn":
                return t`Chinese (Simplified)`;
        }
    };

    return (
        <>
            <Select
                leftSection={<IconWorld size={15} color={'#ccc'}/>}
                width={180}
                size={'xs'}
                required
                data={Object.keys(localeToNameMap).map(locale => ({
                    value: locale,
                    label: getLocaleName(locale as SupportedLocales),
                }))}
                defaultValue={getClientLocale()}
                placeholder={t`English`}
                onChange={(value) =>
                    dynamicActivateLocale(value as string).then(() => {
                        document.cookie = `locale=${value};path=/;max-age=31536000`;
                        // this shouldn't be necessary, but it is due to the wide use of t`...` in the codebase
                        window.location.reload();
                    })}
            />
        </>
    )
}