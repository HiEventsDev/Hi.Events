import {t} from "@lingui/macro";
import classes from "./FloatingPoweredBy.module.scss";
import classNames from "classnames";
import React, {useEffect, useState} from "react";
import {iHavePurchasedALicence, isHiEvents} from "../../../utilites/helpers.ts";
import {getConfig} from "../../../utilites/config.ts";
import {useGetInstanceInfo} from "../../../ee/licensing/queries/useGetInstanceInfo.ts";

const configuredHost = (): string => {
    const frontendUrl = getConfig("VITE_FRONTEND_URL");
    try {
        return frontendUrl ? new URL(frontendUrl).hostname : "unknown";
    } catch {
        return "unknown";
    }
};

const poweredByLink = (host: string, medium: string): string => {
    const url = new URL("https://hi.events");
    url.searchParams.set("utm_source", "app-powered-by-footer");
    url.searchParams.set("utm_medium", isHiEvents() ? medium : 'self-hosted-' + medium);
    url.searchParams.set("utm_campaign", "powered-by");
    url.searchParams.set("utm_content", isHiEvents() ? "hi.events" : host);

    return url.toString();
};

/**
 * (c) Hi.Events Ltd 2024-present
 *
 * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
 * The full licence text is in the LICENCE file in the repository root.
 *
 * Under Section 7(b) of the AGPL, the "Powered by Hi.Events" notice must stay on all web pages
 * and emails. If you modify Hi.Events you may rephrase it, for example "Powered by [Your Company]
 * based on Hi.Events", but it must still link to https://hi.events.
 *
 * The notice must stay clearly visible and legible. Do not hide or obscure it, for example by
 * shrinking its font size, lowering its contrast, matching its colour to the background, covering
 * it or moving it off-screen.
 *
 * To remove the notice you need a commercial licence: https://hi.events/licensing
 * With a licence, hide it through your licence key or configuration rather than by editing this code.
 *
 * Commercial licences help keep Hi.Events free and open source. To keep that fair for everyone who
 * pays, we may work with a third-party compliance partner to find installations that remove or
 * obscure this notice without a licence. If you hear from us or them, it will start as a friendly
 * conversation, and you'll have 30 days to get a licence or restore the notice.
 */
export const PoweredByFooter = (
    props: React.DetailedHTMLProps<React.HTMLAttributes<HTMLDivElement>, HTMLDivElement>
) => {
    const {data: instance, isPending, isError} = useGetInstanceInfo();

    const [link, setLink] = useState(() => poweredByLink(configuredHost(), "app"));

    useEffect(() => {
        setLink(poweredByLink(window.location.hostname, window.location.pathname.includes("/widget") ? "widget" : "app"));
    }, []);

    if (iHavePurchasedALicence() || isPending || (!isError && instance?.white_label)) {
        return <></>;
    }

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
            {instance?.dev_mode && (
                <div className={classes.devLicence}>
                    {t`Development licence, not for production use`}
                </div>
            )}
        </div>
    );
}
