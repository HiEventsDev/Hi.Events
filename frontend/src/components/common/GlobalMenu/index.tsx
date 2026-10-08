import {Avatar, Menu, Text, UnstyledButton} from "@mantine/core";
import {ReactNode} from "react";
import {getInitials} from "../../../utilites/helpers.ts";
import {
    IconLicense,
    IconLifebuoy,
    IconLogout,
    IconPlus,
    IconSettingsCog,
    IconShield,
    IconUser,
    IconUsers,
} from "@tabler/icons-react";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {NavLink} from "react-router";
import {t} from "@lingui/macro";
import {authClient} from "../../../api/auth.client.ts";
import {useDisclosure} from "@mantine/hooks";
import {AboutModal} from "../../modals/AboutModal";
import {getConfig} from "../../../utilites/config.ts";
import {CreateOrganizerModal} from "../../modals/CreateOrganizerModal";
import {useLicenceNotice} from "../../../ee/licensing/hooks/useLicenceNotice.ts";
import {LICENCE_TONE_COLORS, licenceStatusLabel} from "../../../ee/licensing/licenceNotice.ts";
import {LicenceIndicator} from "../../../ee/licensing/components/LicenceIndicator";
import {LicenceModal} from "../../../ee/licensing/components/LicenceModal";

interface Link {
    label: string;
    icon: any;
    link?: string;
    target?: string;
    onClick?: (event: any) => void;
    rightSection?: ReactNode;
    testId?: string;
}

export const GlobalMenu = () => {
    const {data: me} = useGetMe();
    const licenceNotice = useLicenceNotice();
    const [licenceModalOpen, {open: openLicenceModal, close: closeLicenceModal}] = useDisclosure(false);
    const [aboutModalOpen, {open: openAboutModal, close: closeAboutModal}] = useDisclosure(false);
    const [createOrganizerModalOpen, {
        open: openCreateOrganizerModal,
        close: closeCreateOrganizerModal
    }] = useDisclosure(false);


    const links: Link[] = [
        {
            label: t`My Profile`,
            icon: IconUser,
            link: "/manage/profile",
        },
        {
            label: t`Account Settings`,
            icon: IconSettingsCog,
            link: `/account/settings`,
        },
    ];

    if (me?.role === 'ADMIN' || me?.role === 'SUPERADMIN') {
        links.push({
            label: t`User Management`,
            icon: IconUsers,
            link: `/account/users`
        })
    }

    if (me?.role === 'SUPERADMIN') {
        links.push({
            label: t`Admin Dashboard`,
            icon: IconShield,
            link: `/admin`
        })
    }

    if (licenceNotice) {
        links.push({
            label: t`Licence`,
            icon: IconLicense,
            rightSection: (
                <Text size="xs" c={LICENCE_TONE_COLORS[licenceNotice.tone]}>
                    {licenceStatusLabel(licenceNotice.kind)}
                </Text>
            ),
            testId: 'licence-menu-item',
            onClick: (event: any) => {
                event.preventDefault();
                openLicenceModal();
            },
        });
    }

    if (!getConfig("VITE_HIDE_ABOUT_LINK")) {
        links.push({
            label: `About & Support`,
            icon: IconLifebuoy,
            onClick: openAboutModal,
        });
    }

    links.push({
        label: t`Create Organizer`,
        icon: IconPlus,
        onClick: (event: any) => {
            event.preventDefault();
            openCreateOrganizerModal();
        }
    });

    links.push({
        label: t`Logout`,
        icon: IconLogout,
        onClick: async (event: any) => {
            event.preventDefault();
            await authClient.logout();
            localStorage.removeItem("token");
            window.location.href = "/auth/login";
        },
    });

    return (
        <>
            <Menu shadow="md" width={230}>
                <Menu.Target>
                    <UnstyledButton data-testid="account-menu-button">
                        <LicenceIndicator notice={licenceNotice}>
                            <Avatar color={"primary.1"} radius="xl">
                                {me ? getInitials(me.first_name + " " + me.last_name) : ".."}
                            </Avatar>
                        </LicenceIndicator>
                    </UnstyledButton>
                </Menu.Target>

                <Menu.Dropdown>
                    {links.map((link) => (
                        <NavLink
                            onClick={link.onClick}
                            to={link.link ?? "#"}
                            key={link.label}
                            target={link.target ?? ""}
                        >
                            <Menu.Item
                                component={"div"}
                                leftSection={<link.icon/>}
                                rightSection={link.rightSection}
                                data-testid={link.testId}
                            >
                                {link.label}
                            </Menu.Item>
                        </NavLink>
                    ))}
                </Menu.Dropdown>
            </Menu>
            {aboutModalOpen && <AboutModal onClose={closeAboutModal}/>}
            {createOrganizerModalOpen && <CreateOrganizerModal onClose={closeCreateOrganizerModal}/>}
            {licenceModalOpen && licenceNotice && me?.licence && (
                <LicenceModal
                    licence={me.licence}
                    notice={licenceNotice}
                    showDetailsLink={me.role === 'SUPERADMIN'}
                    onClose={closeLicenceModal}
                />
            )}
        </>
    );
};
