import {Select} from "@mantine/core";
import {dynamicActivateLocale, getClientLocale, localeToNameMap, SupportedLocales} from "../../../locales.ts";
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
                    label: localeToNameMap[locale as SupportedLocales]
                }))}
                defaultValue={getClientLocale()}
                placeholder={t`English`}
                onChange={(value) =>
                    dynamicActivateLocale(value as string).then(() => {
                        document.cookie = `locale=${value};path=/;max-age=31536000`;
                        window.location.reload();
                    })}
            />
        </>
    )
}