import {t} from "@lingui/macro";
import classes from "./FloatingPoweredBy.module.scss";
import classNames from "classnames";
import React, {useMemo} from "react";
import {iHavePurchasedALicence, isHiEvents} from "../../../utilites/helpers.ts";
import {getConfig} from "../../../utilites/config.ts";

/**
 * (c) Hi.Events Ltd 2025
 *
 * PLEASE NOTE:
 *
 * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
 *
 * You can find the full license text at: https://github.com/HiEventsDev/hi.events/blob/main/LICENCE
 *
 * In accordance with Section 7(b) of the AGPL, you must retain the "Powered by Hi.Events" notice.
 *
 * If you wish to remove this notice, a commercial license is available at: https://hi.events/licensing
 */
export const PoweredByFooter = (
    props: React.DetailedHTMLProps<React.HTMLAttributes<HTMLDivElement>, HTMLDivElement>
) => {
    if (iHavePurchasedALicence()) {
        return <></>;
    }

    const link = useMemo(() => {
        let host = getConfig("VITE_FRONTEND_URL") ?? "unknown";
        let medium = "app";

        if (typeof window !== "undefined" && window.location) {
            host = window.location.hostname;
            medium = window.location.pathname.includes("/widget") ? "widget" : "app";
        }

        const url = new URL("https://hi.events");
        url.searchParams.set("utm_source", "app-powered-by-footer");
        url.searchParams.set("utm_medium", isHiEvents() ? medium : 'self-hosted-' + medium);
        url.searchParams.set("utm_campaign", "powered-by");
        url.searchParams.set("utm_content", isHiEvents() ? "hi.events" : host);

        return url.toString();
    }, []);

    const footerContent = isHiEvents() ? (
        <>
            {t`Planning an event?`}{" "}
            <a
                href={`${link}`}
                target="_blank"
                className={classes.ctaLink}
                title={"Effortlessly manage events and sell tickets online with Hi.Events"}
            >
                {t`Try Hi.Events Free`}
            </a>
        </>
    ) : (
        <>
            {t`Powered by`}{" "}
            <a
                href={link}
                target="_blank"
                title={"Effortlessly manage events and sell tickets online with Hi.Events"}
            >
                Hi.Events
            </a>{" "}
            🚀
        </>
    );

    return (
        <div {...props} className={classNames(classes.poweredBy, props.className)}>
            <div className={classes.poweredByText}>
                {footerContent}
            </div>
        </div>
    );
}
