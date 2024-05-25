import {t} from "@lingui/macro";
import classes from "./FloatingPoweredBy.module.scss";
import classNames from "classnames";
import React from "react";
import {iHavePurchasedALicence} from "../../../utilites/helpers.ts";

/**
 * PLEASE NOTE:
 *
 * Under the terms of the license, you are not permitted to remove or obscure the powered by footer unless you have a white-label
 * or commercial license.
 * @see https://github.com/HiEventsDev/hi.events/blob/main/LICENCE#L13
 **
 * You can purchase a license at https://hi.events/licensing
 */
export const PoweredByFooter = (props: React.DetailedHTMLProps<React.HTMLAttributes<HTMLDivElement>, HTMLDivElement>) => {
    if (iHavePurchasedALicence()) {
        return <></>;
    }

    return (
        <div {...props} className={classNames(classes.poweredBy, props.className)}>
            {t`Powered by`}{'  '}
            {/* eslint-disable-next-line lingui/no-unlocalized-strings */}
            <a href="https://hi.events?utm_source=footer"
               target="_blank"
               title={'Manage events and Sell tickets online with Hi.Events.'}>
                hi.events 👋
            </a>
        </div>
    );
}