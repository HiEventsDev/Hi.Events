import {CloseButton, Drawer, DrawerProps, Skeleton} from "@mantine/core";
import React, {useId} from "react";
import {t} from "@lingui/macro";
import classes from "./SideDrawer.module.scss";

interface SideDrawerProps extends Omit<DrawerProps, 'position' | 'size' | 'title'> {
    header?: React.ReactNode;
    actions?: React.ReactNode;
    footer?: React.ReactNode;
    loading?: boolean;
}

interface SideDrawerHeadingProps {
    media?: React.ReactNode;
    title: React.ReactNode;
    subtitle?: React.ReactNode;
}

export interface DrawerStat {
    label: string;
    value: React.ReactNode;
}

interface SideDrawerSectionProps {
    title: string;
    count?: number;
    surface?: boolean;
    children: React.ReactNode;
}

export const SideDrawerHeading = ({media, title, subtitle}: SideDrawerHeadingProps) => (
    <div className={classes.heading}>
        {media && <div className={classes.headingMedia}>{media}</div>}
        <div className={classes.headingText}>
            <div className={classes.headingTitle}>{title}</div>
            {subtitle && <div className={classes.headingSubtitle}>{subtitle}</div>}
        </div>
    </div>
);

export const SideDrawerStats = ({stats}: { stats: DrawerStat[] }) => (
    <div className={classes.stats}>
        {stats.map((stat) => (
            <div key={stat.label} className={classes.stat}>
                <div className={classes.statLabel}>{stat.label}</div>
                <div className={classes.statValue}>{stat.value}</div>
            </div>
        ))}
    </div>
);

export const SideDrawerSection = ({title, count, surface, children}: SideDrawerSectionProps) => (
    <section className={classes.section}>
        <div className={classes.sectionHeader}>
            <h3 className={classes.sectionTitle}>{title}</h3>
            {count !== undefined && <span className={classes.sectionCount}>{count}</span>}
        </div>
        {surface ? <div className={classes.surface}>{children}</div> : children}
    </section>
);

export const SideDrawerFields = ({fields}: { fields: DrawerStat[] }) => (
    <div className={classes.fields}>
        {fields.map((field) => (
            <div key={field.label} className={classes.field}>
                <div className={classes.fieldLabel}>{field.label}</div>
                <div className={classes.fieldValue}>{field.value}</div>
            </div>
        ))}
    </div>
);

const HeadingPlaceholder = () => (
    <div className={classes.heading}>
        <Skeleton height={42} circle/>
        <div className={classes.headingText}>
            <Skeleton height={13} width="45%" radius="sm"/>
            <Skeleton height={10} width="70%" radius="sm" mt={10}/>
        </div>
    </div>
);

const ContentPlaceholder = () => (
    <div className={classes.placeholder}>
        <Skeleton height={74} radius="md"/>
        {[45, 70, 55, 65].map((width, index) => (
            <Skeleton key={index} height={12} width={`${width}%`} radius="sm"/>
        ))}
    </div>
);

export const SideDrawer = ({header, actions, footer, loading, children, ...props}: SideDrawerProps) => {
    const headingId = useId();

    return (
        <Drawer
            {...props}
            aria-labelledby={headingId}
            position="right"
            size="xl"
            withCloseButton={false}
            closeOnClickOutside={false}
            overlayProps={{
                opacity: 0.55,
                blur: 3,
            }}
            classNames={{
                content: classes.content,
                body: classes.body,
            }}
        >
            <div className={classes.header} data-bordered={(header || loading) ? true : undefined} id={headingId}>
                {loading ? <HeadingPlaceholder/> : header}
                <div className={classes.headerActions}>
                    {actions}
                    <CloseButton
                        aria-label={t`Close`}
                        onClick={props.onClose}
                        size="lg"
                        radius="xl"
                    />
                </div>
            </div>

            <div className={classes.scrollArea} data-autofocus tabIndex={-1}>
                {loading ? <ContentPlaceholder/> : children}
            </div>

            {footer && <div className={classes.footer}>{footer}</div>}
        </Drawer>
    )
}
