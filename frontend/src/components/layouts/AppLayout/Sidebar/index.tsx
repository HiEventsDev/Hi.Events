import React from "react";
import {Badge, UnstyledButton, VisuallyHidden} from '@mantine/core';
import {IconChevronLeft} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import classes from './Sidebar.module.scss';
import {NavItem} from "../types";
import {NavLink} from "react-router";
import classNames from "classnames";
import {useMediaQuery} from "@mantine/hooks";
import {getConfig} from "../../../../utilites/config.ts";

interface SidebarProps {
    sidebarOpen: boolean;
    setSidebarOpen: (open: boolean) => void;
    navItems: NavItem[];
    sidebarFooter?: React.ReactNode;
}

export const Sidebar: React.FC<SidebarProps> = ({
                                                    sidebarOpen,
                                                    setSidebarOpen,
                                                    navItems,
                                                    sidebarFooter,
                                                }) => {
    const renderLinks = () => {
        return navItems.map((item) => {
            const isMobile = useMediaQuery('(max-width: 768px)');

            if (!item.link && item.link !== "" && item.onClick === undefined) {
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
                return <a key={item.label} className={classNames(classes.loading, classes.link)}>&nbsp;</a>;
            }


            return (
                <NavLink
                    to={item.comingSoon ? '#' : item.link}
                    key={item.label}
                    onClick={() => {
                        if (isMobile) {
                            setSidebarOpen(false);
                        }
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

    return (
        <div className={classNames(`${classes.sidebar} ${sidebarOpen ? classes.open : classes.closed}`)}>
            <div className={classes.logo}>
                <NavLink to={`/manage/events`}>
                    <img
                        style={{maxWidth: '160px', margin: "10px auto"}}
                        src={getConfig("VITE_APP_LOGO_LIGHT", "/logo-wide-white-text.svg")}
                        alt={t`${getConfig("VITE_APP_NAME", "Hi.Events")} logo`}
                    />
                </NavLink>
            </div>
            <div className={classes.nav}>
                {renderLinks()}
            </div>
            {sidebarFooter && (
                <div className={classes.sidebarFooter}>
                    {sidebarFooter}
                </div>
            )}
            {sidebarOpen && (
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
