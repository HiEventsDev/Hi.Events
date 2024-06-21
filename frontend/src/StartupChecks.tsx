import {useGetMe} from "./queries/useGetMe.ts";
import {useEffect} from "react";
import {dynamicActivateLocale, getClientLocale} from "./locales.ts";

export const StartupChecks = () => {
    const meQuery = useGetMe();

    const setLocaleForLoggedInUser = () => {
        const cookieLocale = getClientLocale();

        if (cookieLocale) {
            // If the user has a locale set in their cookies, we don't want to override it
            return;
        }

        if (meQuery.data?.locale) {
            dynamicActivateLocale(meQuery.data.locale).then(() => {
                console.log('Activated locale from user settings ' + meQuery.data.locale);
            });
        }
    };

    useEffect(() => {
        if (!meQuery.isSuccess) {
            return;
        }

        setLocaleForLoggedInUser();
    }, [meQuery.isSuccess]);

    return <></>;
}