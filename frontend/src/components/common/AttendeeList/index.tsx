import {ActionIcon, Avatar, Tooltip} from "@mantine/core";
import {getInitials} from "../../../utilites/helpers.ts";
import Truncate from "../Truncate";
import {NavLink} from "react-router-dom";
import {IconEye} from "@tabler/icons-react";
import classes from './AttendeeList.module.scss';
import {Order, Product} from "../../../types.ts";
import {t} from "@lingui/macro";

export const AttendeeList = ({order, products}: { order: Order, products: Product[] }) => {
    return (
        <div className={classes.attendeeList}>
            {order.attendees?.map(attendee => (
                <div className={classes.attendee}>
                    <Avatar size={40}>
                        {getInitials(attendee.first_name + ' ' + attendee.last_name)}
                    </Avatar>

                    <div className={classes.attendeeName}>
                        {attendee.first_name + ' ' + attendee.last_name}
                        <div className={classes.productName}>
                            <Truncate text={products?.find(product => product.id === attendee.product_id)?.title}/>
                        </div>
                    </div>
                    <div className={classes.viewAttendee}>
                        <Tooltip label={t`Navigate to Attendee`} position={'bottom'} withArrow>
                            <NavLink to={`../attendees?query=${attendee.public_id}`}>
                                <ActionIcon variant={'light'}>
                                    <IconEye/>
                                </ActionIcon>
                            </NavLink>
                        </Tooltip>
                    </div>
                </div>
            ))}
        </div>
    )
}