import {Anchor} from "@mantine/core";
import {Attendee, Product} from "../../../types.ts";
import classes from "./AttendeeDetails.module.scss";
import {t} from "@lingui/macro";
import {getAttendeeProductTitle} from "../../../utilites/products.ts";
import {getLocaleName, SupportedLocales} from "../../../locales.ts";
import {relativeDate} from "../../../utilites/dates.ts";

export const AttendeeDetails = ({attendee}: { attendee: Attendee }) => {
    return (
        <div className={classes.orderDetails}>
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
                    {t`Product`}
                </div>
                <div className={classes.amount}>
                    {getAttendeeProductTitle(attendee, attendee.product as Product)}
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
            {attendee.check_ins && attendee.check_ins.length > 0 && (
                <div className={classes.block}>
                    <div className={classes.title}>
                        {t`Check-Ins`}
                    </div>
                    <div className={classes.value}>
                        {attendee.check_ins.map((checkIn) => (
                            <div key={checkIn.id}>
                                <strong>{checkIn.check_in_list?.name}</strong> - {relativeDate(checkIn.created_at)}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
