import {
    IconArrowsHorizontal,
    IconCalendar,
    IconCalendarPlus,
    IconDashboard,
    IconExternalLink,
    IconPaint,
    IconSettings,
    IconSparkles,
    IconUsersGroup
} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {BreadcrumbItem, NavItem} from "../AppLayout/types.ts";
import AppLayout from "../AppLayout";
import {NavLink, useNavigate, useParams} from "react-router";
import {Button} from "@mantine/core";
import {useGetOrganizer} from "../../../queries/useGetOrganizer.ts";
import {useState} from "react";
import {CreateEventModal} from "../../modals/CreateEventModal";
import {TopBarButton} from "../../common/TopBarButton";
import classes from "./OrganizerLayout.module.scss";
import {CalloutConfig, SidebarCalloutQueue} from "../../common/SidebarCallout/SidebarCalloutQueue";
import {InviteUserModal} from "../../modals/InviteUserModal";
import {useDisclosure} from "@mantine/hooks";
import {SwitchOrganizerModal} from "../../modals/SwitchOrganizerModal";
import {useGetOrganizers} from "../../../queries/useGetOrganizers.ts";

const OrganizerLayout = () => {
    const {organizerId} = useParams();
    const {data: organizer} = useGetOrganizer(organizerId);
    const navigate = useNavigate();
    const [showCreateEventModal, setShowCreateEventModal] = useState(false);
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [switchOrganizerModalOpen, {open: openSwitchModal, close: closeSwitchModal}] = useDisclosure(false);
    const {data: organizerResposne} = useGetOrganizers();
    const organizers = organizerResposne?.data;

    const navItems: NavItem[] = [
        {
            label: t`Switch Organizer`,
            icon: IconArrowsHorizontal,
            onClick: openSwitchModal,
            isActive: () => false,
            showWhen: () => organizers && organizers.length > 1,
        },
        {label: 'Overview'},
        {link: 'dashboard', label: t`Organizer Dashboard`, icon: IconDashboard},

        {label: t`Manage`},
        {link: 'events', label: t`Events`, icon: IconCalendar},
        {link: 'settings', label: t`Settings`, icon: IconSettings},
        {link: 'organizer-homepage-designer', label: t`Homepage Designer`, icon: IconPaint},
    ];

    const navItemsWithLoading: NavItem[] = navItems.map(item => {
        if (!organizer) {
            return {
                ...item,
                loading: true
            };
        }
        return item;
    });

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            link: '/manage/events',
            content: t`Events`
        },
        {
            link: `/manage/organizer/${organizerId}`,
            content: organizer?.name,
        }
    ];

    const callouts: CalloutConfig[] = [
        {
            icon: <IconUsersGroup size={20}/>,
            heading: t`Invite Your Team`,
            description: t`Collaborate with your team to create amazing events together.`,
            buttonIcon: <IconUsersGroup size={16}/>,
            buttonText: t`Invite Team Members`,
            onClick: () => {
                openCreateModal();
            },
            storageKey: `organizer-${organizerId}-team-callout-dismissed`
        },
        {
            icon: <IconSparkles size={20}/>,
            heading: t`Design Your Homepage`,
            description: t`Customize your organizer homepage to showcase your brand.`,
            buttonIcon: <IconPaint size={16}/>,
            buttonText: t`Customize Homepage`,
            onClick: () => navigate(`/manage/organizer/${organizerId}/organizer-homepage-designer`),
            storageKey: `organizer-${organizerId}-homepage-callout-dismissed`
        }
    ];

    return (
        <>
            <AppLayout
                navItems={navItemsWithLoading}
                breadcrumbItems={breadcrumbItems}
                entityType="organizer"
                topBarContent={(
                    <>
                        <TopBarButton
                            onClick={() => setShowCreateEventModal(true)}
                            leftSection={<IconCalendarPlus size={17}/>}
                            title={t`Create Event`}
                            className={classes.createEventButton}
                        >
                            <span className={classes.createEventButtonTextMobile}>
                            {t`Event`}
                            </span>

                            <span className={classes.createEventButtonTextDesktop}>
                            {t`Create Event`}
                            </span>
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
                        <span className={classes.viewHomepageButtonTextDesktop}>
                            {t`View Organizer Homepage`}
                        </span>
                    </Button>
                )}
                sidebarFooter={<SidebarCalloutQueue callouts={callouts}/>}
            />

            {createModalOpen && <InviteUserModal onClose={closeCreateModal}/>}
            {switchOrganizerModalOpen &&
                <SwitchOrganizerModal opened={switchOrganizerModalOpen} onClose={closeSwitchModal}/>}
        </>
    );
};

export default OrganizerLayout;
