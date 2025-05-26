import {
    IconCalendar, IconCalendarPlus,
    IconDashboard,
    IconDeviceDesktopSearch,
    IconExternalLink,
    IconPaint,
    IconSettings
} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {BreadcrumbItem, NavItem} from "../AppLayout/types.ts";
import AppLayout from "../AppLayout";
import {NavLink} from "react-router";
import {Button} from "@mantine/core";

const OrganizerLayout = () => {
    const navItems: NavItem[] = [
        {label: 'Overview'},
        {link: '', label: t`Dashboard`, icon: IconDashboard},

        {label: t`Manage`},
        {link: 'events', label: t`Events`, icon: IconCalendar},
        {link: 'settings', label: t`Settings`, icon: IconSettings},
        {link: 'organizer-homepage-designer', label: t`Organizer Homepage`, icon: IconDeviceDesktopSearch},
    ];

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            link: '/manage/events',
            content: t`Events`
        },
    ];

    return (
        <AppLayout
            navItems={navItems}
            breadcrumbItems={breadcrumbItems}
            entityType="organizer"
            topBarContent={(
                <>
                    <Button
                        component={NavLink}
                        to={`/organizer/`}
                        target={'_blank'}
                        variant={'filled'}
                        color={'green'}
                        leftSection={<IconCalendarPlus size={17}/>}
                        title={t`Create Event`}
                    >
                        {t`Create Event`}
                    </Button>
                </>
            )}
            breadcrumbContentRight={(
                <>
                </>
            )}
            actionGroupContent={(
                <Button
                    component={NavLink}
                    to={`/event/`}
                    target={'_blank'}
                    variant={'transparent'}
                    leftSection={<IconExternalLink size={17}/>}
                    title={t`Organizer Homepage`}
                >
                    <div className={''}>
                        {t`Organizer Homepage`}
                    </div>
                </Button>
            )}
        />
    );
};

export default OrganizerLayout;
