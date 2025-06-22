import {Avatar, Menu, UnstyledButton} from "@mantine/core";
import {getInitials} from "../../../utilites/helpers.ts";
import {IconLifebuoy, IconLogout, IconPlus, IconSettingsCog, IconUser, IconUsers,} from "@tabler/icons-react";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {NavLink} from "react-router";
import {t} from "@lingui/macro";
import {authClient} from "../../../api/auth.client.ts";
import {useDisclosure} from "@mantine/hooks";
import {AboutModal} from "../../modals/AboutModal";
import {getConfig} from "../../../utilites/config.ts";
import {CreateOrganizerModal} from "../../modals/CreateOrganizerModal";

interface Link {
    label: string;
    icon: any;
    link?: string;
    target?: string;
    onClick?: (event: any) => void;
}

export const GlobalMenu = () => {
    const {data: me} = useGetMe();
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

    if (me?.role === 'ADMIN') {
        links.push({
            label: t`User Management`,
            icon: IconUsers,
            link: `/account/users`
        })
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
        onClick: (event: any) => {
            event.preventDefault();
            authClient.logout();
            localStorage.removeItem("token");
            window.location.href = "/auth/login";
        },
    });

    return (
        <>
            <Menu shadow="md" width={200}>
                <Menu.Target>
                    <UnstyledButton>
                        <Avatar color={"primary.1"} radius="xl">
                            {me ? getInitials(me.first_name + " " + me.last_name) : ".."}
                        </Avatar>
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
                            <Menu.Item component={"div"} leftSection={<link.icon/>}>
                                {link.label}
                            </Menu.Item>
                        </NavLink>
                    ))}
                </Menu.Dropdown>
            </Menu>
            {aboutModalOpen && <AboutModal onClose={closeAboutModal}/>}
            {createOrganizerModalOpen && <CreateOrganizerModal onClose={closeCreateOrganizerModal}/>}
        </>
    );
};
