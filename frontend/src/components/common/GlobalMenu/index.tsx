import {Avatar, Menu, UnstyledButton} from "@mantine/core";
import {getInitials, iHavePurchasedALicence, isHiEvents} from "../../../utilites/helpers.ts";
import {IconLogout, IconMoneybag, IconSettingsCog, IconSpeakerphone, IconUser} from "@tabler/icons-react";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {NavLink} from "react-router-dom";
import {t} from "@lingui/macro";
import {authClient} from "../../../api/auth.client.ts";

export const GlobalMenu = () => {
    const {data: me} = useGetMe();

    const links = [
        {
            label: t`My Profile`,
            icon: IconUser,
            link: '/manage/profile',
        },
        {
            label: t`Account Settings`,
            icon: IconSettingsCog,
            link: `/account/settings`,
        },
        ...((iHavePurchasedALicence() || isHiEvents()) ? [] : [
            {
                label: t`Purchase License`,
                icon: IconMoneybag,
                link: 'https://hi.events/licensing?utm_source=app-top-menu',
                target: '_blank',
            }
        ]),
        {
            label: t`Feedback`,
            icon: IconSpeakerphone,
            link: 'mailto:hello@hi.events?subject=Feedback',
            target: '_blank',
        },
        {
            label: t`Logout`,
            icon: IconLogout,
            onClick: (event: any) => {
                event.preventDefault();
                authClient.logout();
                localStorage.removeItem('token');
                window.location.href = '/auth/login';
            }
        }
    ];

    return (
        <Menu shadow="md" width={200}>
            <Menu.Target>
                <UnstyledButton>
                    <Avatar color={'pink'} radius="xl">
                        {me
                            ? getInitials(me.first_name + ' ' + me.last_name)
                            : '..'}
                    </Avatar>
                </UnstyledButton>
            </Menu.Target>

            <Menu.Dropdown>
                {links.map((link) => {
                    return (
                        <NavLink onClick={link.onClick} to={link.link ?? '#'} key={link.label}
                                 target={link.target ?? ''}>
                            <Menu.Item component={'div'} leftSection={<link.icon/>}>{link.label}</Menu.Item>
                        </NavLink>
                    );
                })}
            </Menu.Dropdown>
        </Menu>
    );
}
