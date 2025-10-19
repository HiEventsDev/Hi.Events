import {t} from "@lingui/macro";
import classes from "./FloatingPoweredBy.module.scss";
import classNames from "classnames";
import React from "react";
import {iHavePurchasedALicence, isHiEvents} from "../../../utilites/helpers.ts";

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
export const PoweredByFooter = (props: React.DetailedHTMLProps<React.HTMLAttributes<HTMLDivElement>, HTMLDivElement>) => {
    if (iHavePurchasedALicence()) {
        return <></>;
    }

    const footerContent = isHiEvents() ? (
        <>
            {t`Planning an event?`} {' '}
            <a href="https://hi.events?utm_source=app-powered-by-footer&utm_content=try-hi-events-free"
               target="_blank"
               className={classes.ctaLink}
               title={'Effortlessly manage events and sell tickets online with Hi.Events'}>
                {t`Try Hi.Events Free`}
            </a>
        </>
    ) : (
        <>
            {t`Powered by`} {' '}
            <a href="https://hi.events?utm_source=app-powered-by-footer"
               target="_blank"
               title={'Effortlessly manage events and sell tickets online with Hi.Events'}>
                Hi.Events
            </a> 🚀
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
