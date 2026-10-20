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
    sidebarOpen: boolean | null;
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
    const isMobile = useMediaQuery('(max-width: 767px)');

    const isHeading = (item: NavItem) => !item.link && item.link !== "" && item.onClick === undefined;

    const visibleItems = () => {
        const shown = navItems.filter(item => !item.showWhen || item.showWhen());

        return shown.filter((item, index) => {
            if (!isHeading(item)) {
                return true;
            }
            const next = shown[index + 1];
            return !!next && !isHeading(next);
        });
    };

    const renderLinks = () => {
        return visibleItems().map((item) => {

            if (isHeading(item)) {
                return (
                    <div className={classes.sectionHeading} key={item.label}>
                        {item.label}
                    </div>
                );
            }

            if (item.loading) {
                return <a key={item.label} className={classNames(classes.loading, classes.link)}>&nbsp;</a>;
            }


            return (
                <NavLink
                    to={item.comingSoon ? '#' : (item.link ?? '#')}
                    key={item.link ?? item.label}
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
                    {item.badge !== undefined && (
                        <Badge
                            size="xs"
                            radius="xl"
                            className={item.badgeColor ? classes.navBadgeAlert : classes.navBadge}
                        >
                            {item.badge}
                        </Badge>
                    )}
                    {item.comingSoon &&
                        <Badge ml={'4px'} size={'xs'} className={classes.comingSoonBadge}>{t`Coming Soon`}</Badge>}
                </NavLink>
            );
        });
    };

    return (
        <div
            id="app-manage-sidebar"
            className={classNames(classes.sidebar, {[classes.open]: sidebarOpen === true, [classes.closed]: sidebarOpen === false})}
        >
            <div className={classes.logo}>
                <NavLink to={`/manage/events`}>
                    <img
                        src={getConfig("VITE_APP_LOGO_LIGHT", "/logos/hi-events-text-dark.svg")}
                        alt={t`${getConfig("VITE_APP_NAME", "Hi.Events")} logo`}
                    />
                </NavLink>
            </div>
            <nav className={classes.nav} aria-label={t`Main navigation`}>
                {renderLinks()}
            </nav>
            {sidebarFooter && (
                <div className={classes.sidebarFooter}>
                    {sidebarFooter}
                </div>
            )}
            {sidebarOpen !== false && (
                <UnstyledButton
                    className={classes.sidebarClose}
                    onClick={() => setSidebarOpen(false)}>
                    <IconChevronLeft size={20}/>
                    <VisuallyHidden>{t`Close sidebar`}</VisuallyHidden>
                </UnstyledButton>
            )}
        </div>
    );
};
