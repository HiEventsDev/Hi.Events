import React from "react";
import {NavLink} from "react-router";
import {Breadcrumbs, Burger} from '@mantine/core';
import classes from './Topbar.module.scss';
import {BreadcrumbItem} from "../types";
import {GlobalMenu} from "../../../common/GlobalMenu";
import { getConfig } from "../../../../utilites/config";

interface TopbarProps {
    sidebarOpen: boolean;
    setSidebarOpen: (open: boolean) => void;
    topBarShadow: boolean;
    breadcrumbItems: BreadcrumbItem[];
    topBarContent?: React.ReactNode;
    breadcrumbContentRight?: React.ReactNode;
    actionGroupContent?: React.ReactNode;
}

export const Topbar: React.FC<TopbarProps> = ({
                                                  sidebarOpen,
                                                  setSidebarOpen,
                                                  topBarShadow,
                                                  breadcrumbItems,
                                                  topBarContent = null,
                                                  breadcrumbContentRight = null,
                                                  actionGroupContent = null,
                                              }) => {
    return (
        <div className={`${classes.topBar} ${topBarShadow ? classes.withShadow : ''}`}>
            <div className={classes.topBarMain}>
                <div className={classes.burger}>
                    <Burger
                        color={'#fff'}
                        opened={sidebarOpen}
                        onClick={() => setSidebarOpen(!sidebarOpen)}
                        size={'sm'}
                    />
                </div>
                <div className={classes.logo}>
                    <NavLink to={ `/manage/events` }>
                        <img src={getConfig("VITE_APP_LOGO_LIGHT", "/logos/hi-events-text-dark.svg")} alt={`${getConfig("VITE_APP_NAME", "Hi.Events")} logo`} className={classes.logo}/>
                    </NavLink>
                </div>

                {topBarContent}
                <div className={classes.actionGroup}>
                    {actionGroupContent}

                    <div className={classes.menu}>
                        <GlobalMenu/>
                    </div>
                </div>
            </div>

            <div className={classes.breadcrumbsRow}>
                <div className={classes.breadcrumbs}>
                    <Breadcrumbs separator={<span style={{margin: '0 4px', color: '#aaa'}}>/</span>}>
                        {breadcrumbItems.map((item, index) => (
                            <NavLink key={index} to={item.link ?? '#'}>
                                {item.content}
                            </NavLink>
                        ))}
                    </Breadcrumbs>
                </div>
                {breadcrumbContentRight && (
                    <div className={classes.breadcrumbContentRight}>
                        {breadcrumbContentRight}
                    </div>
                )}
            </div>
        </div>
    );
};
