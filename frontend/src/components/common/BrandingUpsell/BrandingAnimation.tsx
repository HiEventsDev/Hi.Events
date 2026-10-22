import {t} from "@lingui/macro";
import classNames from "classnames";
import classes from "./BrandingAnimation.module.scss";

interface BrandingAnimationProps {
    className?: string;
}

export const BrandingAnimation = ({className}: BrandingAnimationProps) => (
    <div className={classNames(classes.stage, className)}>
        <svg
            viewBox="0 0 400 210"
            role="img"
            aria-label={t`Hi.Events branding is removed from your event page, and your logo replaces it in emails`}
            className={classes.animation}
        >
            <g transform="translate(14 16)">
                <rect className={classes.surface} width="226" height="178" rx="12"/>
                <rect className={classes.cover} x="12" y="12" width="202" height="56" rx="7"/>
                <rect className={classes.lineStrong} x="12" y="80" width="118" height="9" rx="3"/>
                <rect className={classes.line} x="12" y="96" width="176" height="6" rx="3"/>
                <rect className={classes.line} x="12" y="107" width="140" height="6" rx="3"/>
                <rect className={classes.button} x="12" y="124" width="74" height="20" rx="6"/>
                <line className={classes.hairline} x1="12" x2="214" y1="156" y2="156"/>
                <rect className={classes.line} x="160" y="164" width="22" height="5" rx="2.5"/>
                <rect className={classes.line} x="188" y="164" width="26" height="5" rx="2.5"/>
                <g className={classes.pageFooter}>
                    <text className={classes.footerText} x="12" y="169">{t`Powered by`} Hi.Events</text>
                </g>
            </g>

            <g transform="translate(206 40)">
                <rect className={classes.shadow} x="2" y="4" width="180" height="156" rx="12"/>
                <rect className={classes.surface} width="180" height="156" rx="12"/>
                <g className={classes.hiLogo}>
                    <text className={classes.wordmark} x="90" y="27" textAnchor="middle">HI.EVENTS</text>
                </g>
                <g className={classes.organizerLogo}>
                    <rect className={classes.logoMark} x="76" y="11" width="28" height="22" rx="6"/>
                    <text className={classes.logoLetter} x="90" y="27" textAnchor="middle">A</text>
                </g>
                <rect className={classes.inner} x="12" y="42" width="156" height="66" rx="7"/>
                <rect className={classes.lineStrong} x="22" y="52" width="84" height="7" rx="3"/>
                <rect className={classes.line} x="22" y="65" width="128" height="5" rx="2.5"/>
                <rect className={classes.line} x="22" y="75" width="104" height="5" rx="2.5"/>
                <rect className={classes.button} x="22" y="88" width="58" height="12" rx="4"/>
                <g className={classes.promo}>
                    <rect className={classes.lineStrong} x="54" y="120" width="72" height="5" rx="2.5"/>
                    <rect className={classes.line} x="40" y="130" width="100" height="4" rx="2"/>
                    <rect className={classes.link} x="66" y="140" width="48" height="4" rx="2"/>
                </g>
            </g>
        </svg>
    </div>
);
