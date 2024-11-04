import { ActionIcon, Avatar, Tooltip, Text, Group } from "@mantine/core";
import { getInitials } from "../../../utilites/helpers.ts";
import { NavLink } from "react-router-dom";
import { IconExternalLink, IconUsers } from "@tabler/icons-react";
import classes from './AttendeeList.module.scss';
import { Order, Product } from "../../../types.ts";
import { t } from "@lingui/macro";

interface AttendeeListProps {
    order: Order;
    products: Product[];
}

export const AttendeeList = ({ order, products }: AttendeeListProps) => {
    const attendeeCount = order.attendees?.length || 0;

    if (!order.attendees?.length) {
        return (
            <div className={classes.container}>
                <Text size="sm" c="dimmed" ta="center" py="xl">
                    {t`No attendees found for this order.`}
                </Text>
            </div>
        );
    }

    return (
        <div className={classes.container}>
            <div className={classes.attendeeList}>
                {order.attendees.map(attendee => {
                    const product = products?.find(p => p.id === attendee.product_id);
                    const fullName = `${attendee.first_name} ${attendee.last_name}`;

                    return (
                        <div key={attendee.id} className={classes.attendee}>
                            <div className={classes.attendeeInfo}>
                                <Avatar
                                    size="md"
                                    radius="xl"
                                    className={classes.avatar}
                                >
                                    {getInitials(fullName)}
                                </Avatar>

                                <div className={classes.details}>
                                    <Text size="sm" fw={500} className={classes.name}>
                                        {fullName}
                                    </Text>
                                    {product?.title && (
                                        <Text size="xs" className={classes.product} lineClamp={1}>
                                            {product.title}
                                        </Text>
                                    )}
                                </div>

                                <Tooltip
                                    label={t`View Attendee Details`}
                                    position="bottom"
                                    withArrow
                                >
                                    <NavLink to={`../attendees?query=${attendee.public_id}`}>
                                        <ActionIcon
                                            variant="subtle"
                                            radius="xl"
                                            size="sm"
                                            className={classes.actionButton}
                                        >
                                            <IconExternalLink size={16} stroke={1.5} />
                                        </ActionIcon>
                                    </NavLink>
                                </Tooltip>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};
