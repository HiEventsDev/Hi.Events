import React from "react";
import {Badge, UnstyledButton, VisuallyHidden} from '@mantine/core';
import {IconChevronLeft} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import classes from './Sidebar.module.scss';
import {NavItem} from "../types";
import {NavLink} from "react-router";

interface SidebarProps {
    sidebarOpen: boolean;
    setSidebarOpen: (open: boolean) => void;
    navItems: NavItem[];
}

export const Sidebar: React.FC<SidebarProps> = ({
                                                    sidebarOpen,
                                                    setSidebarOpen,
                                                    navItems,
                                                }) => {
    const renderLinks = () => {
        return navItems.map((item) => {
            if (!item.link && item.link !== "") {
                return (
                    <div className={classes.sectionHeading} key={item.label}>
                        {item.label}
                    </div>
                );
            }

            if (item.showWhen && !item.showWhen()) {
                return null;
            }

            if (item.loading) {
                return <div key={item.label} className={classes.loading}></div>;
            }

            return (
                <NavLink
                    to={item.comingSoon ? '#' : item.link}
                    key={item.label}
                    onClick={() => {
                        setSidebarOpen(false);
                        if (item.onClick) item.onClick();
                    }}
                    className={({isActive}) =>
                        `${((item.isActive ? item.isActive(isActive) : isActive) && !item.comingSoon)
                            ? classes.linkActive
                            : ""} ${classes.link}`
                    }
                >
                    {item.icon && <item.icon size={20} className={classes.linkIcon} stroke={1.5}/>}
                    <span>{item.label}</span>
                    {item.badge !== undefined &&
                        <Badge size="xs" radius="xl" className={classes.navBadge}>{item.badge}</Badge>}
                    {item.comingSoon &&
                        <Badge ml={'4px'} size={'xs'} className={classes.comingSoonBadge}>{t`Coming Soon`}</Badge>}
                </NavLink>
            );
        });
    };

    if (sidebarOpen) {
        return (
            <UnstyledButton
                className={classes.sidebarOpen}
                onClick={() => setSidebarOpen(!sidebarOpen)}>
                <IconChevronLeft size={20}/>
                <VisuallyHidden>{t`Open sidebar`}</VisuallyHidden>
            </UnstyledButton>
        );
    }

    return (
        <div className={classes.sidebar}>
            <div className={classes.logo}>
                <NavLink to={`/manage/events`}>
                    <img style={{maxWidth: '160px', margin: "10px auto"}}
                         src={'/logo-wide-white-text.svg'} alt={''}/>
                </NavLink>
            </div>
            <div className={classes.nav}>
                {renderLinks()}
            </div>
            {!sidebarOpen && (
                <UnstyledButton
                    className={classes.sidebarClose}
                    onClick={() => setSidebarOpen(!sidebarOpen)}>
                    <IconChevronLeft size={20}/>
                    <VisuallyHidden>{t`Close sidebar`}</VisuallyHidden>
                </UnstyledButton>
            )}
        </div>
    );
};
