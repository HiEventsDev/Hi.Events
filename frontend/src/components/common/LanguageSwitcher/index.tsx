import {Select} from "@mantine/core";
import {
    dynamicActivateLocale,
    getClientLocale,
    getLocaleName,
    localeToNameMap,
    SupportedLocales
} from "../../../locales.ts";
import {t} from "@lingui/macro";
import {IconWorld} from "@tabler/icons-react";

export const LanguageSwitcher = () => {
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