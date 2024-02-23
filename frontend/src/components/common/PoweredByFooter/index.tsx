import {t} from "@lingui/macro";
import classes from "./FloatingPoweredBy.module.scss";

export const PoweredByFooter = () => {
    return (
        <div className={classes.poweredBy}>
            {t`Powered by`}{'  '}
            {/* eslint-disable-next-line lingui/no-unlocalized-strings */}
            <a href="https://hi.events?utm_source=footer" target="_blank">
                 hi.events 👋
            </a>
        </div>
    );
}