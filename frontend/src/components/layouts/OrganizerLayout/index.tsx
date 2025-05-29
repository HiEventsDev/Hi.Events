import {
    IconCalendar,
    IconCalendarPlus,
    IconDashboard,
    IconExternalLink,
    IconPaint,
    IconSettings
} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {BreadcrumbItem, NavItem} from "../AppLayout/types.ts";
import AppLayout from "../AppLayout";
import {NavLink, useParams} from "react-router";
import {Button} from "@mantine/core";
import {useGetOrganizer} from "../../../queries/useGetOrganizer.ts";
import {useState} from "react";
import {CreateEventModal} from "../../modals/CreateEventModal";
import {TopBarButton} from "../../common/TopBarButton";
import classes from "./OrganizerLayout.module.scss";

const OrganizerLayout = () => {
    const {organizerId} = useParams();
    const {data: organizer} = useGetOrganizer(organizerId);
    const [showCreateEventModal, setShowCreateEventModal] = useState(false);

    const navItems: NavItem[] = [
        {label: 'Overview'},
        {link: '', label: t`Dashboard`, icon: IconDashboard},

        {label: t`Manage`},
        {link: 'events', label: t`Events`, icon: IconCalendar},
        {link: 'settings', label: t`Settings`, icon: IconSettings},
        {link: 'organizer-homepage-designer', label: t`Homepage Designer`, icon: IconPaint},
    ];

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            link: '/manage/events',
            content: t`Events`
        },
        {
            link: `/dashboard/${organizerId}/${organizer?.slug || ''}`,
            content: organizer?.name || t`Organizer`
        }
    ];

    return (
        <AppLayout
            navItems={navItems}
            breadcrumbItems={breadcrumbItems}
            entityType="organizer"
            topBarContent={(
                <>
                    <TopBarButton
                        onClick={() => setShowCreateEventModal(true)}
                        leftSection={<IconCalendarPlus size={17}/>}
                        title={t`Create Event`}
                    >
                        {t`Create Event`}
                    </TopBarButton>
                    {showCreateEventModal && (
                        <CreateEventModal
                            onClose={() => setShowCreateEventModal(false)}
                            organizerId={organizerId}
                        />
                    )}
                </>
            )}
            breadcrumbContentRight={(
                <>
                </>
            )}
            actionGroupContent={(
                <Button
                    component={NavLink}
                    to={`/events/${organizerId}/${organizer?.slug || ''}`}
                    target={'_blank'}
                    variant={'transparent'}
                    className={classes.viewHomepageButton}
                    leftSection={<IconExternalLink size={17}/>}
                    title={t`View Organizer Homepage`}
                >
                    {t`View Homepage`}
                </Button>
            )}
        />
    );
};

export default OrganizerLayout;
