import {Button, Group, Menu, Text,} from '@mantine/core';
import {Event} from "../../../types.ts";
import classes from "./EventCard.module.scss";
import {Card} from "../Card";
import {NavLink, useNavigate} from "react-router-dom";
import {
    IconCalendarEvent,
    IconDotsVertical,
    IconEye,
    IconMap,
    IconQrcode,
    IconSettings,
    IconUser,
} from "@tabler/icons-react";
import {relativeDate} from "../../../utilites/dates.ts";
import {t} from "@lingui/macro"
import {eventHomepagePath} from "../../../utilites/urlHelper.ts";
import {EventStatusBadge} from "../EventStatusBadge";

interface EventCardProps {
    event: Event;
}

export function EventCard({event}: EventCardProps) {
    const navigate = useNavigate();

    return (
        <>
            <Card className={classes.card}>
                <div className={classes.body}>
                    {event && <EventStatusBadge event={event}/>}
                    <Text className={classes.title} mt="xs" mb="md">
                        <NavLink to={`/manage/event/${event.id}`}>
                            {event.title}
                        </NavLink>
                    </Text>
                    <div className={classes.eventInfo}>
                        {event.settings?.location_details?.venue_name && (
                            <Group gap="xs" wrap="nowrap">
                                <IconMap color={'#ccc'}/>
                                <Text size="xs">
                                    {event.settings?.location_details?.venue_name}
                                </Text>
                            </Group>
                        )}
                        {event.settings?.is_online_event && (
                            <Group gap="xs" wrap="nowrap">
                                <IconMap color={'#ccc'}/>
                                <Text size="xs">
                                    {t`Online event`}
                                </Text>
                            </Group>
                        )}
                        <Group gap="xs" wrap="nowrap">
                            <IconCalendarEvent color={'#ccc'}/>
                            <Text size="xs">
                                {relativeDate(event.start_date)}
                            </Text>
                        </Group>
                        <Group gap="xs" wrap="nowrap">
                            <IconUser color={'#ccc'}/>
                            <Text size="xs">
                                <NavLink to={`/manage/organizer/${event?.organizer?.id}`}>
                                    {event?.organizer?.name}
                                </NavLink>
                            </Text>
                        </Group>
                    </div>
                </div>
                <div className={classes.actions}>
                    <Menu shadow="md" width={200}>
                        <Menu.Target>
                            <div>
                                <Button className={classes.desktopButton} size={"xs"} variant={"transparent"}>
                                    <IconDotsVertical/>
                                </Button>
                                <Button className={classes.mobileButton} variant={"light"}>
                                    {t`Manage`}
                                </Button>
                            </div>
                        </Menu.Target>

                        <Menu.Dropdown>
                            <Menu.Item leftSection={<IconEye size={14}/>}
                                       onClick={() => window.location.href = eventHomepagePath(event)}>
                                {t`View event page`}
                            </Menu.Item>
                            <Menu.Item onClick={() => navigate(`/manage/event/${event.id}`)}
                                       leftSection={<IconSettings size={14}/>}
                            >{t`Manage event`}</Menu.Item>
                            <Menu.Item onClick={() => navigate(`/manage/event/${event.id}/check-in`)}
                                       leftSection={<IconQrcode size={14}/>}
                            >{t`Check-in`}</Menu.Item>

                        </Menu.Dropdown>
                    </Menu>
                </div>
            </Card>
        </>
    );
}