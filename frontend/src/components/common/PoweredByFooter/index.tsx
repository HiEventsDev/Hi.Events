import {t} from "@lingui/macro";
import classes from "./FloatingPoweredBy.module.scss";
import classNames from "classnames";
import React from "react";

/**
 * PLEASE NOTE:
 *
 * Under the terms of the license, you are not allowed to remove the powered by footer.
 * @see https://github.com/HiEventsDev/hi.events/blob/main/LICENCE#L13
 *
 * Only remove the powered by footer if you have paid for a white-label or commercial license.
 *
 * You can purchase a license at https://hi.events/licensing
 */
export const PoweredByFooter = (props: React.DetailedHTMLProps<React.HTMLAttributes<HTMLDivElement>, HTMLDivElement>) => {
    return (
        <div {...props} className={classNames(classes.poweredBy, props.className)}>
            {t`Powered by`}{'  '}
            {/* eslint-disable-next-line lingui/no-unlocalized-strings */}
            <a href="https://hi.events?utm_source=footer" target="_blank">
                hi.events 👋
            </a>
        </div>
    );
}