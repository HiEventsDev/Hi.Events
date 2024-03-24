import {Avatar, Menu, UnstyledButton} from "@mantine/core";
import {getInitials} from "../../../utilites/helpers.ts";
import {IconInfoCircle, IconLogout, IconSettingsCog, IconSpeakerphone, IconUser} from "@tabler/icons-react";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {NavLink, useNavigate} from "react-router-dom";
import {t} from "@lingui/macro";
import {authClient} from "../../../api/auth.client.ts"; // Added import for translation

export const GlobalMenu = () => {
    const {data: me} = useGetMe();
    const navigate = useNavigate();

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
        {
            label: t`About hi.events`,
            icon: IconInfoCircle,
            link: '/about',
        },
        {
            label: t`Feedback`,
            icon: IconSpeakerphone,
            link: 'mailto:hello@hi.events?subject=Feedback',
        },
        {
            label: t`Logout`,
            icon: IconLogout,
            onClick: (event: any) => {
                event.preventDefault();
                localStorage.removeItem('token');
                navigate('/auth/login', {replace: true});
                authClient.logout();
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
                        <NavLink onClick={link.onClick} to={link.link ?? '#'} key={link.label}>
                            <Menu.Item component={'div'} leftSection={<link.icon/>}>{link.label}</Menu.Item>
                        </NavLink>
                    );
                })}
            </Menu.Dropdown>
        </Menu>
    );
}
