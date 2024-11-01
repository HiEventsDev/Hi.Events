import {Anchor} from "@mantine/core";
import {Attendee} from "../../../types.ts";
import classes from "./AttendeeDetails.module.scss";
import {t} from "@lingui/macro";
import {getAttendeeProductTitle} from "../../../utilites/products.ts";
import {getLocaleName, SupportedLocales} from "../../../locales.ts";

export const AttendeeDetails = ({attendee}: { attendee: Attendee }) => {
    return (
        <div className={classes.orderDetails} variant={'lightGray'}>
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Name`}
                </div>
                <div className={classes.amount}>
                    {attendee.first_name} {attendee.last_name}
                </div>
            </div>
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Email`}
                </div>
                <div className={classes.value}>
                    <Anchor href={'mailto:' + attendee.email} target={'_blank'}>{attendee.email}</Anchor>
                </div>
            </div>
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Status`}
                </div>
                <div className={classes.amount}>
                    {attendee.status}
                </div>
            </div>
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Checked In`}
                </div>
                <div className={classes.amount}>
                    {attendee.checked_in_at ? t`Yes` : t`No`}
                </div>
            </div>
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Product`}
                </div>
                <div className={classes.amount}>
                    {getAttendeeProductTitle(attendee)}
                </div>
            </div>
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Language`}
                </div>
                <div className={classes.amount}>
                    {getLocaleName(attendee.locale as SupportedLocales)}
                </div>
            </div>
        </div>
    );
}
