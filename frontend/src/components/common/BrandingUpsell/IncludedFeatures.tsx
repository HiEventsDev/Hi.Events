import {t} from "@lingui/macro";
import {IconCheck} from "@tabler/icons-react";
import classes from "./BrandingUpsell.module.scss";

export const IncludedFeatures = () => (
    <ul className={classes.features}>
        <li>
            <IconCheck size={16} stroke={2.25} aria-hidden/>
            {t`No Hi.Events branding on your event pages, organizer pages, checkout or embedded widgets`}
        </li>
        <li>
            <IconCheck size={16} stroke={2.25} aria-hidden/>
            {t`Your organizer logo at the top of order and ticket emails, in place of the Hi.Events logo`}
        </li>
        <li>
            <IconCheck size={16} stroke={2.25} aria-hidden/>
            {t`Emails sent under your organizer name, with buttons and links in your event's colour`}
        </li>
        <li>
            <IconCheck size={16} stroke={2.25} aria-hidden/>
            {t`No Hi.Events promotion in the emails your attendees receive`}
        </li>
    </ul>
);
